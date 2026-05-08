import type { FastifyPluginAsync } from "fastify";
import { z } from "zod";
import { userFromRequest } from "../../shared/auth.js";
import { store } from "../../shared/datastore.js";
import { fail, ok } from "../../shared/http.js";
import { QWEN_VL3B_MODEL, QWEN_VL7B_MODEL } from "../../shared/model-selection.js";
import { getPedagoLiveSqlDiagnostics } from "../../shared/postgres-pedagolive.js";
import { checkEmbeddingHealth } from "../../shared/embedding-client.js";
import { checkQdrantHealth, getQdrantConfig } from "../../shared/qdrant-client.js";
import { getAssistantFeedbackAnalytics } from "../pedagolive/service.js";

let ragDiagnosticsCache: { expiresAt: number; value: unknown } | null = null;

const settingsSchema = z.object({
  aiModel: z.string().min(2).max(60),
  aiTone: z.string().min(2).max(60),
  language: z.string().min(2).max(12),
  reportDetail: z.coerce.number().int().min(1).max(5),
  proactiveAi: z.coerce.boolean(),
  notifProgress: z.coerce.boolean(),
  notifWeekly: z.coerce.boolean(),
  notifSms: z.coerce.boolean(),
  darkMode: z.coerce.boolean(),
  institution: z.string().max(180),
  department: z.string().max(180)
});

const adminIaSchema = z.object({
  provider: z.enum(["openai", "anthropic", "azure_openai", "local_vllm"]),
  liveContextMode: z.enum(["external_service", "lmstudio_ocr_qwen3b", "lmstudio_vl7b_unified"]).default("lmstudio_ocr_qwen3b"),
  chatModelMode: z.enum(["default", "vl7b_only", "vl3b_only"]).default("default"),
  apiBaseUrl: z.string().url(),
  model: z.string().min(2).max(120),
  visionModel: z.string().min(2).max(160).default("qwen2.5-vl-7b-instruct-abliterated"),
  forceVisionModelOnly: z.coerce.boolean().default(false),
  embeddingModel: z.string().min(2).max(120),
  mockMode: z.coerce.boolean().default(false),
  liveContextServiceUrl: z.union([z.string().url(), z.literal("")]).default(""),
  liveContextServiceToken: z.string().max(240).default(""),
  liveContextTimeoutMs: z.coerce.number().int().min(1000).max(120000).default(15000),
  sttServiceUrl: z.union([z.string().url(), z.literal("")]).default(""),
  sttServiceToken: z.string().max(240).default(""),
  sttTimeoutMs: z.coerce.number().int().min(400).max(120000).default(2500),
  sttLanguage: z.string().min(2).max(20).default("fr"),
  ocrCpuThreads: z.coerce.number().int().min(1).max(64).default(1),
  ocrMaxConcurrentJobs: z.coerce.number().int().min(1).max(64).default(1),
  ocrMaxImageMb: z.coerce.number().int().min(1).max(32).default(4),
  contextWindowTokens: z.coerce.number().int().min(1024).max(64000),
  temperature: z.coerce.number().min(0).max(1),
  maxTokens: z.coerce.number().int().min(128).max(32768),
  timeoutMs: z.coerce.number().int().min(3000).max(180000),
  ragEnabled: z.coerce.boolean(),
  ragTopK: z.coerce.number().int().min(1).max(40),
  ragMinScore: z.coerce.number().min(0).max(1),
  ragByProfileEnabled: z.coerce.boolean(),
  rerankerEnabled: z.coerce.boolean(),
  safeMode: z.coerce.boolean()
});

const studentTutorAdminSchema = z.object({
  enabled: z.coerce.boolean(),
  systemPrompt: z.string().min(20).max(6000),
  safetyPrompt: z.string().min(20).max(4000),
  qualityPrompt: z.string().min(20).max(4000),
  defaultExplanationLevel: z.enum(["concise", "standard", "detailed"]),
  allowDeterministicFallback: z.coerce.boolean(),
  maxHistoryMessages: z.coerce.number().int().min(0).max(20)
});

export const settingsRoutes: FastifyPluginAsync = async (app) => {
  async function runJsonProbe(url: string, timeoutMs: number, init?: RequestInit) {
    const startedAt = Date.now();
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), Math.max(400, timeoutMs));
    try {
      const response = await fetch(url, {
        method: init?.method ?? "GET",
        headers: init?.headers,
        body: init?.body,
        signal: controller.signal
      });
      const latencyMs = Date.now() - startedAt;
      const json = await response.json().catch(() => null);
      return {
        ok: response.ok,
        statusCode: response.status,
        latencyMs,
        message: response.ok ? "ok" : `HTTP ${response.status}`,
        data: json
      };
    } catch (error) {
      const latencyMs = Date.now() - startedAt;
      const reason = error instanceof Error ? error.message : "probe_failed";
      return { ok: false, statusCode: 0, latencyMs, message: reason, data: null };
    } finally {
      clearTimeout(timer);
    }
  }

  async function buildProviderDiagnostics() {
    const settings = store.aiAdminSettings;
    const timeoutMs = Math.max(500, Number(settings.timeoutMs || 5000));
    const sttTimeoutMs = Math.max(400, Number(settings.sttTimeoutMs || 2500));
    const liveTimeoutMs = Math.max(1000, Number(settings.liveContextTimeoutMs || 15000));
    const baseHeaders: Record<string, string> = { "content-type": "application/json" };

    const llmUrl = settings.apiBaseUrl?.trim() ? `${settings.apiBaseUrl.replace(/\/+$/, "")}/models` : "";
    const sttUrl = settings.sttServiceUrl?.trim() ? `${settings.sttServiceUrl.replace(/\/+$/, "")}/health` : "";
    const liveContextUrl =
      settings.liveContextMode === "external_service"
        ? settings.liveContextServiceUrl?.trim()
          ? `${settings.liveContextServiceUrl.replace(/\/+$/, "")}/health`
          : ""
        : llmUrl;

    const [llmProbe, sttProbe, liveProbe] = await Promise.all([
      llmUrl ? runJsonProbe(llmUrl, timeoutMs, { headers: baseHeaders }) : Promise.resolve(null),
      sttUrl
        ? runJsonProbe(sttUrl, sttTimeoutMs, {
            headers: settings.sttServiceToken ? { ...baseHeaders, authorization: `Bearer ${settings.sttServiceToken}` } : baseHeaders
          })
        : Promise.resolve(null),
      liveContextUrl
        ? runJsonProbe(liveContextUrl, liveTimeoutMs, {
            headers:
              settings.liveContextMode === "external_service" && settings.liveContextServiceToken
                ? { ...baseHeaders, authorization: `Bearer ${settings.liveContextServiceToken}` }
                : baseHeaders
          })
        : Promise.resolve(null)
    ]);

    const llmMode = settings.mockMode ? "mock" : llmProbe?.ok ? "real" : "degraded";
    const sttMode = settings.sttServiceUrl ? (sttProbe?.ok ? "real" : "degraded") : "degraded";
    const liveMode = settings.mockMode ? "mock" : liveProbe?.ok ? "real" : "degraded";

    return {
      checkedAt: new Date().toISOString(),
      effectiveMode: settings.mockMode ? "mock" : "real",
      llm: {
        mode: llmMode,
        endpoint: llmUrl || "",
        statusCode: llmProbe?.statusCode ?? 0,
        latencyMs: llmProbe?.latencyMs ?? null,
        message: llmProbe?.message ?? "apiBaseUrl manquante"
      },
      stt: {
        mode: sttMode,
        endpoint: sttUrl || "",
        statusCode: sttProbe?.statusCode ?? 0,
        latencyMs: sttProbe?.latencyMs ?? null,
        message: sttProbe?.message ?? "sttServiceUrl manquante"
      },
      liveContext: {
        mode: liveMode,
        endpoint: liveContextUrl || "",
        statusCode: liveProbe?.statusCode ?? 0,
        latencyMs: liveProbe?.latencyMs ?? null,
        message:
          liveProbe?.message ??
          (settings.liveContextMode === "external_service" ? "liveContextServiceUrl manquante" : "apiBaseUrl manquante")
      }
    } as const;
  }

  function buildLiveRuntimeStatus() {
    const settings = store.aiAdminSettings;
    const isMock = settings.mockMode === true;
    const hasLlm = Boolean(settings.apiBaseUrl && settings.model);
    const liveContextFromLmstudio =
      settings.liveContextMode === "lmstudio_ocr_qwen3b" || settings.liveContextMode === "lmstudio_vl7b_unified";
    const hasLiveContext = liveContextFromLmstudio
      ? Boolean(settings.apiBaseUrl && settings.visionModel)
      : Boolean(settings.liveContextServiceUrl);
    const hasStt = Boolean(settings.sttServiceUrl);

    return {
      effectiveMode: isMock ? "mock" : "real",
      llm: {
        mode: isMock ? "mock" : hasLlm ? "real" : "degraded",
        reason: isMock
          ? "mockMode actif"
          : hasLlm
            ? settings.chatModelMode === "vl3b_only"
              ? "ok (VL3B only actif)"
              : settings.chatModelMode === "vl7b_only" || settings.forceVisionModelOnly
                ? "ok (VL7B only actif)"
              : "ok"
            : "apiBaseUrl/model manquants"
      },
      liveContext: {
        mode: isMock ? "mock" : hasLiveContext ? "real" : "degraded",
        reason: isMock
          ? "mockMode actif"
          : hasLiveContext
            ? liveContextFromLmstudio
              ? "ok (LM Studio vision)"
              : "ok"
            : liveContextFromLmstudio
              ? "apiBaseUrl/visionModel manquants"
              : "liveContextServiceUrl manquante"
      },
      stt: {
        mode: hasStt ? "real" : "degraded",
        reason: hasStt ? "ok" : "sttServiceUrl manquante"
      }
    } as const;
  }

  function buildStudentTutorHistory(limit = 80) {
    const feedbackByMessageId = new Map(
      store.pedagoLiveStudentAssistantFeedback.map((entry) => [entry.assistantMessageId, entry] as const)
    );
    return store.pedagoLiveStudentAssistantMessages
      .filter((entry) => entry.role === "assistant")
      .slice(-Math.max(1, Math.min(200, limit)))
      .reverse()
      .map((answer) => {
        const question = [...store.pedagoLiveStudentAssistantMessages]
          .reverse()
          .find(
            (entry) =>
              entry.role === "student" &&
              entry.sessionId === answer.sessionId &&
              entry.studentId === answer.studentId &&
              Date.parse(entry.createdAt) <= Date.parse(answer.createdAt)
          );
        const session = store.pedagoLiveSessions.find((entry) => entry.id === answer.sessionId);
        const student = store.users.find((entry) => entry.id === answer.studentId);
        const feedback = feedbackByMessageId.get(answer.id);
        return {
          id: answer.id,
          sessionId: answer.sessionId,
          sessionTitle: session?.title ?? "Session inconnue",
          studentId: answer.studentId,
          studentName: student?.displayName ?? "Eleve inconnu",
          studentDifficulties: student?.difficulties ?? [],
          question: question?.text ?? "",
          answer: answer.text,
          source: answer.source ?? "deterministic",
          blockedFinalAnswer: answer.blockedFinalAnswer ?? false,
          feedback: feedback?.feedback ?? null,
          feedbackReason: feedback?.reason ?? "",
          createdAt: answer.createdAt
        };
      });
  }

  function buildInstitutionalOverview() {
    const teachers = store.users.filter((user) => user.role === "pedagolens_teacher");
    const students = store.users.filter((user) => user.role === "pedagolens_student");
    const assistantMessages = store.pedagoLiveStudentAssistantMessages;
    const assistantAnswers = assistantMessages.filter((entry) => entry.role === "assistant");
    const assistantQuestions = assistantMessages.filter((entry) => entry.role === "student");
    const twinQuestions = store.twinSessions.flatMap((session) => session.messages.filter((msg) => msg.role === "user"));
    const twinAnswers = store.twinSessions.flatMap((session) => session.messages.filter((msg) => msg.role === "assistant"));
    const helpedStudentIds = new Set<string>([
      ...store.pedagoLiveParticipants.map((entry) => entry.userId),
      ...assistantMessages.map((entry) => entry.studentId),
      ...store.twinSessions.map((entry) => entry.studentId),
      ...store.tutorSignals.map((entry) => entry.studentId)
    ]);
    const now = new Date();
    const monthBuckets = new Map<
      string,
      {
        key: string;
        label: string;
        studentsHelped: Set<string>;
        questionsAnswered: number;
        promptCount: number;
        liveSignals: number;
        feedbackUseful: number;
        feedbackTotal: number;
        scoreTotal: number;
        scoreCount: number;
      }
    >();

    for (let i = 5; i >= 0; i -= 1) {
      const date = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - i, 1));
      const key = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
      const label = new Intl.DateTimeFormat("fr-CA", { month: "short", timeZone: "UTC" }).format(date);
      monthBuckets.set(key, {
        key,
        label,
        studentsHelped: new Set<string>(),
        questionsAnswered: 0,
        promptCount: 0,
        liveSignals: 0,
        feedbackUseful: 0,
        feedbackTotal: 0,
        scoreTotal: 0,
        scoreCount: 0
      });
    }

    function bucketKeyFromIso(value?: string): string {
      if (!value) return "";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
    }

    function withBucket(value: string | undefined, callback: (bucket: NonNullable<ReturnType<typeof monthBuckets.get>>) => void) {
      const bucket = monthBuckets.get(bucketKeyFromIso(value));
      if (bucket) callback(bucket);
    }

    const difficultyCounts = new Map<string, number>();
    for (const student of students) {
      const values = [...(student.difficulties ?? []), student.difficultyOtherText ?? ""]
        .map((item) => item.trim())
        .filter(Boolean);
      if (!values.length) {
        difficultyCounts.set("non_declared", (difficultyCounts.get("non_declared") ?? 0) + 1);
        continue;
      }
      for (const value of values) difficultyCounts.set(value, (difficultyCounts.get(value) ?? 0) + 1);
    }

    const scoreAgg = new Map<string, { total: number; count: number }>();
    for (const analysis of store.analyses) {
      for (const [key, value] of Object.entries(analysis.profileScores ?? {})) {
        if (!Number.isFinite(value)) continue;
        const agg = scoreAgg.get(key) ?? { total: 0, count: 0 };
        agg.total += value;
        agg.count += 1;
        scoreAgg.set(key, agg);
      }
    }
    const scoreMass = Array.from(scoreAgg.values()).reduce((acc, entry) => acc + (entry.count ? entry.total / entry.count : 0), 0);
    const diversificationScores = Array.from(scoreAgg.entries())
      .map(([key, entry]) => {
        const average = entry.count ? Math.round(entry.total / entry.count) : 0;
        return {
          key,
          average,
          count: entry.count,
          percentageOfScoreMix: scoreMass ? Number(((average / scoreMass) * 100).toFixed(1)) : 0
        };
      })
      .sort((a, b) => b.average - a.average);

    const feedbackAnalytics = getAssistantFeedbackAnalytics({});
    for (const participant of store.pedagoLiveParticipants) {
      if (participant.role !== "student") continue;
      withBucket(participant.joinedAt, (bucket) => bucket.studentsHelped.add(participant.userId));
    }
    for (const message of assistantMessages) {
      withBucket(message.createdAt, (bucket) => {
        bucket.studentsHelped.add(message.studentId);
        if (message.role === "student") bucket.promptCount += 1;
        if (message.role === "assistant") bucket.questionsAnswered += 1;
      });
    }
    for (const session of store.twinSessions) {
      for (const message of session.messages) {
        withBucket(message.sentAt, (bucket) => {
          bucket.studentsHelped.add(session.studentId);
          if (message.role === "user") bucket.promptCount += 1;
          if (message.role === "assistant") bucket.questionsAnswered += 1;
        });
      }
    }
    for (const signal of store.pedagoLiveSignalEvents) {
      withBucket(signal.createdAt, (bucket) => {
        bucket.studentsHelped.add(signal.actorUserId);
        bucket.liveSignals += 1;
      });
    }
    for (const feedback of store.pedagoLiveStudentAssistantFeedback) {
      withBucket(feedback.createdAt, (bucket) => {
        bucket.feedbackTotal += 1;
        if (feedback.feedback === "useful") bucket.feedbackUseful += 1;
      });
    }
    for (const analysis of store.analyses) {
      const values = Object.values(analysis.profileScores ?? {}).filter((value) => Number.isFinite(value));
      if (!values.length) continue;
      withBucket(analysis.createdAt, (bucket) => {
        bucket.scoreTotal += values.reduce((acc, value) => acc + value, 0) / values.length;
        bucket.scoreCount += 1;
      });
    }

    const timeSeries = Array.from(monthBuckets.values()).map((bucket) => ({
      key: bucket.key,
      label: bucket.label,
      studentsHelped: bucket.studentsHelped.size,
      questionsAnswered: bucket.questionsAnswered,
      promptCount: bucket.promptCount,
      liveSignals: bucket.liveSignals,
      feedbackUsefulRate: bucket.feedbackTotal ? Number((bucket.feedbackUseful / bucket.feedbackTotal).toFixed(3)) : 0,
      averageScore: bucket.scoreCount ? Math.round(bucket.scoreTotal / bucket.scoreCount) : 0
    }));

    const heatmapColumns = [
      { key: "not_understood", label: "Pas compris" },
      { key: "need_rephrase", label: "Reformuler" },
      { key: "too_fast", label: "Trop vite" },
      { key: "need_concrete_example", label: "Exemple" },
      { key: "need_summary", label: "Resume" },
      { key: "need_recap_previous", label: "Recap" }
    ];
    const sessionsById = new Map(store.pedagoLiveSessions.map((session) => [session.id, session]));
    const signalRows = new Map<string, { sessionId: string; label: string; values: Record<string, number> }>();
    for (const signal of store.pedagoLiveSignalEvents) {
      const session = sessionsById.get(signal.sessionId);
      const label = session?.title ?? signal.sessionId;
      const row = signalRows.get(signal.sessionId) ?? {
        sessionId: signal.sessionId,
        label: label.length > 22 ? `${label.slice(0, 21)}...` : label,
        values: Object.fromEntries(heatmapColumns.map((column) => [column.key, 0]))
      };
      row.values[signal.signalType] = (row.values[signal.signalType] ?? 0) + 1;
      signalRows.set(signal.sessionId, row);
    }
    const liveSignalHeatmap = {
      columns: heatmapColumns,
      rows: Array.from(signalRows.values())
        .sort((a, b) => Object.values(b.values).reduce((acc, value) => acc + value, 0) - Object.values(a.values).reduce((acc, value) => acc + value, 0))
        .slice(0, 5)
    };

    const teacherRows = teachers.map((teacher) => {
      const sessions = store.pedagoLiveSessions.filter((session) => session.teacherId === teacher.id);
      const sessionIds = new Set(sessions.map((session) => session.id));
      const groups = store.pedagoLiveGroups.filter((group) => group.teacherId === teacher.id);
      const groupIds = new Set(groups.map((group) => group.id));
      const groupStudentIds = new Set(
        store.pedagoLiveGroupMemberships.filter((membership) => groupIds.has(membership.groupId)).map((membership) => membership.userId)
      );
      return {
        teacherId: teacher.id,
        teacherName: teacher.displayName,
        email: teacher.email,
        courses: new Set(groups.map((group) => group.courseId)).size,
        groups: groups.length,
        sessions: sessions.length,
        studentsHelped: groupStudentIds.size,
        questionsAnswered: assistantAnswers.filter((entry) => sessionIds.has(entry.sessionId)).length,
        signals: store.pedagoLiveSignalEvents.filter((entry) => sessionIds.has(entry.sessionId)).length
      };
    });

    return {
      updatedAt: new Date().toISOString(),
      totals: {
        teachers: teachers.length,
        students: students.length,
        studentsHelped: helpedStudentIds.size,
        courses: store.courses.length + store.pedagoLiveCourses.length,
        projects: store.projects.length,
        analyses: store.analyses.length,
        liveGroups: store.pedagoLiveGroups.length,
        liveSessions: store.pedagoLiveSessions.length,
        questionsAnswered: assistantAnswers.length + twinAnswers.length,
        promptCount: assistantQuestions.length + twinQuestions.length,
        liveSignals: store.pedagoLiveSignalEvents.length,
        feedbackCount: store.pedagoLiveStudentAssistantFeedback.length,
        usefulRate: feedbackAnalytics.totals.usefulRate
      },
      difficultyDistribution: Array.from(difficultyCounts.entries())
        .map(([key, count]) => ({
          key,
          count,
          percentage: students.length ? Number(((count / students.length) * 100).toFixed(1)) : 0
        }))
        .sort((a, b) => b.count - a.count),
      diversificationScores,
      timeSeries,
      liveSignalHeatmap,
      feedbackAnalytics,
      teachers: teacherRows.sort((a, b) => b.studentsHelped - a.studentsHelped),
      recentTutorHistory: buildStudentTutorHistory(12)
    };
  }

  async function buildRagDiagnostics() {
    const now = Date.now();
    if (ragDiagnosticsCache && ragDiagnosticsCache.expiresAt > now) {
      return ragDiagnosticsCache.value;
    }
    const started = Date.now();
    const [embedding, qdrant] = await Promise.all([checkEmbeddingHealth(), checkQdrantHealth()]);
    const latencyMs = Date.now() - started;
    const chunks = store.pedagoLiveCourseRagChunks ?? [];
    const documents = store.pedagoLiveDocuments ?? [];
    const coursesById = new Map(store.pedagoLiveCourses.map((course) => [course.id, course.title]));
    const chunksByDocument = new Map<string, typeof chunks>();
    for (const chunk of chunks) {
      const current = chunksByDocument.get(chunk.documentId) ?? [];
      current.push(chunk);
      chunksByDocument.set(chunk.documentId, current);
    }
    const indexedDocuments = documents.filter((doc) => doc.ragStatus === "indexed").length;
    const failedDocuments = documents.filter((doc) => doc.ragStatus === "failed").length;
    const pendingDocuments = documents.filter((doc) => doc.ragStatus === "pending" || !doc.ragStatus).length;
    const readyChunks = chunks.filter((chunk) => chunk.embeddingStatus === "ready").length;
    const failedChunks = chunks.filter((chunk) => chunk.embeddingStatus === "failed").length;
    const lexicalOnlyChunks = chunks.filter((chunk) => chunk.embeddingStatus === "lexical_only" || chunk.vectorBackend === "none").length;
    const lastIndexedAt =
      documents
        .map((doc) => doc.indexedAt ?? doc.uploadedAt)
        .filter((value): value is string => Boolean(value))
        .sort()
        .at(-1) ?? null;
    const vectorEnabled = embedding.configured && qdrant.configured;
    const health =
      vectorEnabled && embedding.ok && qdrant.ok
        ? "up"
        : vectorEnabled && (embedding.ok || qdrant.ok)
          ? "degraded"
          : chunks.length
            ? "degraded"
            : "disabled";
    const qdrantConfig = getQdrantConfig();

    const diagnostics = {
      enabled: vectorEnabled,
      health,
      vectorStatus: qdrant.ok ? "up" : qdrant.configured ? "down" : "disabled",
      backendName: qdrant.configured ? `qdrant:${qdrantConfig.collection}` : "lexical",
      totalChunks: chunks.length,
      totalDocuments: documents.length,
      indexedDocuments,
      pendingDocuments,
      failedDocuments,
      avgLatencyMs: latencyMs,
      p95LatencyMs: latencyMs,
      lastIndexedAt,
      lastCheckedAt: new Date().toISOString(),
      counts: {
        chunks: chunks.length,
        readyChunks,
        failedChunks,
        lexicalOnlyChunks,
        documents: documents.length,
        indexedDocuments,
        pendingDocuments,
        failedDocuments
      },
      vector: {
        embedding,
        qdrant,
        collection: qdrantConfig.collection,
        vectorSize: qdrantConfig.vectorSize
      },
      errorDocuments: documents
        .filter((doc) => doc.ragStatus === "failed" || doc.ragError || (chunksByDocument.get(doc.id) ?? []).some((chunk) => chunk.embeddingStatus === "failed"))
        .slice(0, 40)
        .map((doc) => {
          const docChunks = chunksByDocument.get(doc.id) ?? [];
          return {
            id: doc.id,
            title: doc.title,
            courseTitle: doc.courseId ? coursesById.get(doc.courseId) ?? doc.courseId : "Cours non lie",
            status: doc.ragStatus,
            error: doc.ragError ?? docChunks.find((chunk) => chunk.embeddingError)?.embeddingError,
            updatedAt: doc.indexedAt ?? doc.uploadedAt,
            chunks: docChunks.length,
            failedChunks: docChunks.filter((chunk) => chunk.embeddingStatus === "failed").length
          };
        })
    };
    ragDiagnosticsCache = {
      expiresAt: Date.now() + 15_000,
      value: diagnostics
    };
    return diagnostics;
  }

  app.get("/teacher", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifié.", 401);

    const settings = store.settingsByUser.get(user.id) ?? {
      aiModel: "elite",
      aiTone: "academic",
      language: "fr",
      reportDetail: 4,
      proactiveAi: true,
      notifProgress: true,
      notifWeekly: true,
      notifSms: false,
      darkMode: false,
      institution: "",
      department: ""
    };

    return ok(reply, { settings });
  });

  app.put("/teacher", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifié.", 401);
    if (user.role === "pedagolens_student") return fail(reply, "Accès refusé.", 403);

    const parsed = settingsSchema.safeParse(request.body);
    if (!parsed.success) return fail(reply, "Payload de paramètres invalide.");

    store.settingsByUser.set(user.id, parsed.data);
    return ok(reply, { settings: parsed.data });
  });

  app.get("/admin/ia", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    return ok(reply, { settings: store.aiAdminSettings, runtimeStatus: buildLiveRuntimeStatus() });
  });

  app.get("/admin/student-tutor", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    return ok(reply, {
      settings: store.studentTutorAdminSettings,
      analytics: getAssistantFeedbackAnalytics({ windowDays: 30 }),
      history: buildStudentTutorHistory(40)
    });
  });

  app.put("/admin/student-tutor", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    const parsed = studentTutorAdminSchema.safeParse(request.body);
    if (!parsed.success) return fail(reply, "Payload tuteur eleve invalide.");

    store.studentTutorAdminSettings = {
      ...parsed.data,
      updatedAt: new Date().toISOString()
    };
    return ok(reply, {
      settings: store.studentTutorAdminSettings,
      analytics: getAssistantFeedbackAnalytics({ windowDays: 30 }),
      history: buildStudentTutorHistory(40)
    });
  });

  app.get("/admin/student-tutor/history", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    return ok(reply, { history: buildStudentTutorHistory(120) });
  });

  app.get("/institutional/overview", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role === "pedagolens_student") return fail(reply, "Acces refuse.", 403);

    return ok(reply, { overview: buildInstitutionalOverview() });
  });

  app.put("/admin/ia", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    const parsed = adminIaSchema.safeParse(request.body);
    if (!parsed.success) return fail(reply, "Payload IA admin invalide.");

    const next = { ...parsed.data };
    if (next.liveContextMode === "lmstudio_vl7b_unified") {
      next.model = QWEN_VL7B_MODEL;
      next.visionModel = QWEN_VL7B_MODEL;
    } else if (next.liveContextMode === "lmstudio_ocr_qwen3b") {
      next.model = "qwen2.5-3b-instruct";
      if (!next.visionModel?.trim()) {
        next.visionModel = QWEN_VL7B_MODEL;
      }
    }
    if (next.chatModelMode === "vl3b_only") {
      next.model = QWEN_VL3B_MODEL;
      next.visionModel = QWEN_VL3B_MODEL;
      next.forceVisionModelOnly = false;
    } else if (next.chatModelMode === "vl7b_only" || next.forceVisionModelOnly) {
      next.chatModelMode = "vl7b_only";
      next.model = QWEN_VL7B_MODEL;
      next.visionModel = QWEN_VL7B_MODEL;
      next.forceVisionModelOnly = true;
    } else {
      next.forceVisionModelOnly = false;
    }

    store.aiAdminSettings = {
      ...next,
      updatedAt: new Date().toISOString()
    };
    return ok(reply, { settings: store.aiAdminSettings, runtimeStatus: buildLiveRuntimeStatus() });
  });

  app.get("/admin/sql-diagnostics", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    const diagnostics = await getPedagoLiveSqlDiagnostics();
    return ok(reply, { diagnostics });
  });

  app.get("/admin/rag-diagnostics", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    const diagnostics = await buildRagDiagnostics();
    return ok(reply, { diagnostics });
  });

  app.get("/admin/provider-diagnostics", async (request, reply) => {
    const user = userFromRequest(request);
    if (!user) return fail(reply, "Non authentifie.", 401);
    if (user.role !== "administrator") return fail(reply, "Acces refuse.", 403);

    const diagnostics = await buildProviderDiagnostics();
    return ok(reply, { diagnostics });
  });
};
