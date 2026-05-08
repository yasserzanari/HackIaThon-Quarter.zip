"use client";

import { useEffect, useMemo, useState } from "react";
import { WpAppShell } from "@/features/app-shell/wp-app-shell";
import { getToken, getUser } from "@/lib/client-auth";

type Course = {
  id: string;
  code: string;
  title: string;
};

type Project = {
  id: string;
};

type Recommendation = {
  text: string;
};

type Analysis = {
  id: string;
  createdAt: string;
  profileScores?: Record<string, number>;
  recommendations?: Recommendation[];
};

type InstitutionalOverview = {
  updatedAt: string;
  totals: {
    teachers: number;
    students: number;
    studentsHelped: number;
    courses: number;
    projects: number;
    analyses: number;
    liveGroups: number;
    liveSessions: number;
    questionsAnswered: number;
    promptCount: number;
    liveSignals: number;
    feedbackCount: number;
    usefulRate: number;
  };
  difficultyDistribution: Array<{ key: string; count: number; percentage: number }>;
  diversificationScores: Array<{ key: string; average: number; count: number; percentageOfScoreMix: number }>;
  timeSeries?: Array<{
    key: string;
    label: string;
    studentsHelped: number;
    questionsAnswered: number;
    promptCount: number;
    liveSignals: number;
    feedbackUsefulRate: number;
    averageScore: number;
  }>;
  liveSignalHeatmap?: {
    columns: Array<{ key: string; label: string }>;
    rows: Array<{ sessionId: string; label: string; values: Record<string, number> }>;
  };
  teachers: Array<{
    teacherId: string;
    teacherName: string;
    email: string;
    courses: number;
    groups: number;
    sessions: number;
    studentsHelped: number;
    questionsAnswered: number;
    signals: number;
  }>;
};

const profileLabels: Record<string, string> = {
  concentration_tdah: "Concentration TDAH",
  surcharge_cognitive: "Surcharge cognitive",
  langue_seconde: "Langue seconde",
  faible_autonomie: "Faible autonomie",
  anxieux_consignes: "Anxiete consignes",
  avance_rapide: "Avance rapide",
  usage_passif_ia: "Usage passif IA"
};

const profileColors: Record<string, string> = {
  concentration_tdah: "var(--pl-secondary)",
  surcharge_cognitive: "var(--pl-primary)",
  langue_seconde: "var(--pl-tertiary)",
  faible_autonomie: "var(--pl-primary-light)",
  anxieux_consignes: "#f59e0b",
  avance_rapide: "#6366f1",
  usage_passif_ia: "#06b6d4"
};

function monthKey(dateIso: string): string {
  const date = new Date(dateIso);
  if (Number.isNaN(date.getTime())) return "";
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
}

function clamp(value: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, value));
}

function trendPercent(values: number[]): number {
  const first = values.find((value) => value > 0) ?? 0;
  const last = [...values].reverse().find((value) => value > 0) ?? 0;
  if (!first && !last) return 0;
  if (!first) return 100;
  return Math.round(((last - first) / Math.max(1, first)) * 100);
}

function seriesPath(values: number[], width = 220, height = 54): string {
  const max = Math.max(1, ...values);
  return values
    .map((value, index) => {
      const x = values.length <= 1 ? width : (index / (values.length - 1)) * width;
      const y = height - (value / max) * (height - 8) - 4;
      return `${index === 0 ? "M" : "L"} ${x.toFixed(1)} ${y.toFixed(1)}`;
    })
    .join(" ");
}

function heatClass(value: number, max: number): string {
  if (!value || !max) return "heat-empty";
  const ratio = value / max;
  if (ratio >= 0.8) return "heat-4";
  if (ratio >= 0.55) return "heat-3";
  if (ratio >= 0.3) return "heat-2";
  return "heat-1";
}

export function InstitutionalScreen() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [analyses, setAnalyses] = useState<Analysis[]>([]);
  const [overview, setOverview] = useState<InstitutionalOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    const user = getUser();
    if (!user) {
      window.location.href = "/connexion";
      return;
    }
    if (user.role === "pedagolens_student") {
      window.location.href = "/dashboard-etudiant";
      return;
    }

    (async () => {
      try {
        const token = getToken();
        const headers = { authorization: `Bearer ${token}` };

        const [overviewRes, coursesRes, projectsRes, analysesRes] = await Promise.all([
          fetch("/api/settings/institutional/overview", { headers }),
          fetch("/api/courses", { headers }),
          fetch("/api/projects", { headers }),
          fetch("/api/analyses")
        ]);

        const [overviewJson, coursesJson, projectsJson, analysesJson] = await Promise.all([
          overviewRes.json().catch(() => null),
          coursesRes.json().catch(() => null),
          projectsRes.json().catch(() => null),
          analysesRes.json().catch(() => null)
        ]);

        if (!coursesRes.ok || !projectsRes.ok || !analysesRes.ok) {
          throw new Error("Impossible de charger les indicateurs institutionnels.");
        }

        if (overviewRes.ok && overviewJson?.success) {
          setOverview(overviewJson.data?.overview ?? null);
        }
        setCourses(coursesJson?.data?.courses ?? []);
        setProjects(projectsJson?.data?.projects ?? []);
        setAnalyses(analysesJson?.data?.analyses ?? []);
      } catch (fetchError) {
        const message =
          fetchError instanceof Error ? fetchError.message : "Erreur de chargement.";
        setError(message);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const metrics = useMemo(() => {
    const scoreTotals: Record<string, number> = {};
    const scoreCounts: Record<string, number> = {};
    const recCounts: Record<string, number> = {};

    const now = new Date();
    const monthly: Record<string, { total: number; count: number }> = {};
    for (let i = 5; i >= 0; i -= 1) {
      const date = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() - i, 1));
      const key = `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}`;
      monthly[key] = { total: 0, count: 0 };
    }

    for (const analysis of analyses) {
      const scores = analysis.profileScores ?? {};
      const values = Object.values(scores).filter((value) => Number.isFinite(value));
      if (values.length) {
        const global = values.reduce((acc, value) => acc + value, 0) / values.length;
        const key = monthKey(analysis.createdAt);
        if (monthly[key]) {
          monthly[key].total += global;
          monthly[key].count += 1;
        }
      }

      for (const [key, value] of Object.entries(scores)) {
        if (!Number.isFinite(value)) continue;
        scoreTotals[key] = (scoreTotals[key] ?? 0) + value;
        scoreCounts[key] = (scoreCounts[key] ?? 0) + 1;
      }

      for (const recommendation of analysis.recommendations ?? []) {
        const short = recommendation.text.trim().slice(0, 80);
        if (!short) continue;
        recCounts[short] = (recCounts[short] ?? 0) + 1;
      }
    }

    const profileAverages = Object.keys(profileLabels).map((key) => ({
      key,
      label: profileLabels[key],
      avg: scoreCounts[key] ? Math.round(scoreTotals[key] / scoreCounts[key]) : 0,
      color: profileColors[key] ?? "var(--pl-primary)"
    }));

    const globalAvg = profileAverages.length
      ? Math.round(
          profileAverages.reduce((acc, entry) => acc + entry.avg, 0) / profileAverages.length
        )
      : 0;

    const monthlyAverages = Object.entries(monthly).map(([key, data]) => {
      const value = data.count ? Math.round(data.total / data.count) : 0;
      const date = new Date(`${key}-01T00:00:00.000Z`);
      const month = new Intl.DateTimeFormat("fr-CA", {
        month: "short",
        timeZone: "UTC"
      }).format(date);
      return { key, value, month };
    });

    const chartMax = Math.max(1, ...monthlyAverages.map((entry) => entry.value));

    const topRecommendations = Object.entries(recCounts)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([text, count]) => ({ text, count }));

    const impacted = [...profileAverages].sort((a, b) => a.avg - b.avg).slice(0, 3);

    return {
      globalAvg,
      profileAverages,
      monthlyAverages,
      chartMax,
      topRecommendations,
      impacted
    };
  }, [analyses]);

  const executive = useMemo(() => {
    const timeSeries = overview?.timeSeries?.length
      ? overview.timeSeries
      : metrics.monthlyAverages.map((month) => ({
          key: month.key,
          label: month.month,
          studentsHelped: 0,
          questionsAnswered: 0,
          promptCount: 0,
          liveSignals: 0,
          feedbackUsefulRate: 0,
          averageScore: month.value
        }));
    const studentsHelpedSeries = timeSeries.map((entry) => entry.studentsHelped);
    const questionsSeries = timeSeries.map((entry) => entry.questionsAnswered);
    const signalsSeries = timeSeries.map((entry) => entry.liveSignals);
    const feedbackSeries = timeSeries.map((entry) => Math.round(entry.feedbackUsefulRate * 100));
    const scoreSeries = timeSeries.map((entry) => entry.averageScore);
    const feedbackComponent =
      (overview?.totals.feedbackCount ?? 0) > 0 ? (overview?.totals.usefulRate ?? 0) * 100 : 0;
    const reachComponent =
      (overview?.totals.students ?? 0) > 0
        ? clamp((overview?.totals.studentsHelped ?? 0) / (overview?.totals.students ?? 1), 0, 1) *
          100
        : 0;
    const health = clamp(
      Math.round(metrics.globalAvg * 0.62 + feedbackComponent * 0.24 + reachComponent * 0.14),
      0,
      100
    );
    const riskRows = metrics.profileAverages
      .map((profile) => ({
        label: profile.label,
        value: profile.avg,
        color: profile.avg < 55 ? "#ef4444" : profile.avg < 70 ? "#f97316" : profile.avg < 82 ? "#f5b800" : "#14b8a6"
      }))
      .sort((a, b) => a.value - b.value)
      .slice(0, 4);
    const teacherRows = overview?.teachers ?? [];
    const heatmapColumns = overview?.liveSignalHeatmap?.columns ?? [];
    const heatmapRows = overview?.liveSignalHeatmap?.rows ?? [];
    const heatmapMax = Math.max(1, ...heatmapRows.flatMap((row) => Object.values(row.values)));

    return {
      health,
      healthDelta: trendPercent(scoreSeries),
      timeSeries,
      studentsHelpedSeries,
      questionsSeries,
      signalsSeries,
      feedbackSeries,
      scoreSeries,
      riskRows,
      teacherRows,
      heatmapColumns,
      heatmapRows,
      heatmapMax
    };
  }, [metrics.globalAvg, metrics.monthlyAverages, metrics.profileAverages, overview]);

  return (
    <WpAppShell active="institutional" breadcrumb="Lumiere institutionnelle" hideFooter>
      <div className="pl-inst pl-inst-exec">
        <div className="pl-inst-inner pl-inst-exec-inner">
          <header className="pl-inst-exec-header">
            <div>
              <h1 className="pl-inst-exec-title">Lumiere institutionnelle</h1>
              <p className="pl-inst-exec-subtitle">
                Vue strategique de la sante pedagogique et de l&apos;impact de PedagoLens dans votre etablissement.
              </p>
            </div>
            <div className="pl-inst-exec-actions" aria-label="Filtres institutionnels">
              <button type="button" className="pl-inst-filter-btn">
                <span className="material-symbols-outlined">calendar_month</span>
                28 avr. - 25 mai 2026
                <span className="material-symbols-outlined">expand_more</span>
              </button>
              <button type="button" className="pl-inst-filter-btn">
                Tous les niveaux
                <span className="material-symbols-outlined">expand_more</span>
              </button>
              <button type="button" className="pl-inst-export-btn">
                <span className="material-symbols-outlined">download</span>
                Exporter
              </button>
            </div>
          </header>

          {loading ? (
            <div className="pl-inst-empty">
              <span className="material-symbols-outlined">progress_activity</span>
              <p>Chargement des donnees...</p>
            </div>
          ) : null}

          {!loading && error ? (
            <div className="pl-inst-empty">
              <span className="material-symbols-outlined">error</span>
              <p>{error}</p>
            </div>
          ) : null}

          {!loading && !error ? (
            <>
              <section className="pl-inst-hero-grid">
                <article className="pl-inst-health-card">
                  <div className="pl-inst-health-ring" style={{ "--health": executive.health } as React.CSSProperties}>
                    <div className="pl-inst-health-ring-inner">
                      <strong>{executive.health}</strong>
                      <span>/100</span>
                    </div>
                  </div>
                  <div className="pl-inst-health-copy">
                    <p className="pl-inst-card-eyebrow">Sante pedagogique</p>
                    <h2>Bonne</h2>
                    <p>
                      Score global base sur l&apos;engagement, la progression et les signaux de comprehension.
                    </p>
                    <div className="pl-inst-health-deltas">
                      <span>
                        <span className="material-symbols-outlined">{executive.healthDelta < 0 ? "trending_down" : "trending_up"}</span>
                        {executive.healthDelta > 0 ? "+" : ""}{executive.healthDelta}% vs debut de periode
                      </span>
                      <span>
                        <span className="material-symbols-outlined">monitoring</span>
                        {overview?.totals.analyses ?? 0} analyses et {overview?.totals.liveSessions ?? 0} lives inclus
                      </span>
                    </div>
                    <button type="button" className="pl-inst-detail-btn">
                      Voir le detail
                      <span className="material-symbols-outlined">chevron_right</span>
                    </button>
                  </div>
                </article>

                {[
                  {
                    icon: "groups",
                    label: "Eleves aides",
                    value: overview?.totals.studentsHelped ?? 0,
                    delta: trendPercent(executive.studentsHelpedSeries),
                    tone: "green",
                    series: executive.studentsHelpedSeries
                  },
                  {
                    icon: "chat",
                    label: "Questions repondues",
                    value: overview?.totals.questionsAnswered ?? 0,
                    delta: trendPercent(executive.questionsSeries),
                    tone: "blue",
                    series: executive.questionsSeries
                  },
                  {
                    icon: "monitor_heart",
                    label: "Signaux live",
                    value: overview?.totals.liveSignals ?? 0,
                    delta: trendPercent(executive.signalsSeries),
                    tone: "orange",
                    series: executive.signalsSeries
                  },
                  {
                    icon: "thumb_up",
                    label: "Feedback utile",
                    value: `${Math.round((overview?.totals.usefulRate ?? 0) * 100)}%`,
                    delta: trendPercent(executive.feedbackSeries),
                    tone: "violet",
                    series: executive.feedbackSeries
                  }
                ].map((kpi) => (
                  <article key={kpi.label} className={`pl-inst-exec-kpi pl-inst-exec-kpi--${kpi.tone}`}>
                    <div className="pl-inst-exec-kpi-icon">
                      <span className="material-symbols-outlined">{kpi.icon}</span>
                    </div>
                    <span className="pl-inst-exec-kpi-label">{kpi.label}</span>
                    <strong>{typeof kpi.value === "number" ? kpi.value.toLocaleString("fr-CA") : kpi.value}</strong>
                    <span className="pl-inst-kpi-delta">
                      <span className="material-symbols-outlined">{kpi.delta < 0 ? "trending_down" : "trending_up"}</span>
                      {kpi.delta > 0 ? "+" : ""}{kpi.delta}%
                    </span>
                    <svg className="pl-inst-sparkline-svg" viewBox="0 0 220 54" aria-label={`Evolution ${kpi.label}`}>
                      <path d={seriesPath(kpi.series)} />
                    </svg>
                  </article>
                ))}
              </section>

              <section className="pl-inst-main-grid">
                <div className="pl-inst-left-stack">
                  <article className="pl-inst-panel pl-inst-trend-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Tendance sur 6 mois</h2>
                      <button type="button" className="pl-inst-mini-select">Mensuel <span className="material-symbols-outlined">expand_more</span></button>
                    </div>
                    <div className="pl-inst-legend">
                      <span><i className="pl-inst-dot pl-inst-dot--green" />Eleves aides</span>
                      <span><i className="pl-inst-dot pl-inst-dot--blue" />Questions repondues</span>
                      <span><i className="pl-inst-dot pl-inst-dot--orange" />Signaux live</span>
                      <span><i className="pl-inst-dot pl-inst-dot--violet" />Feedback utile (%)</span>
                    </div>
                    <div className="pl-inst-line-chart">
                      <svg className="pl-inst-line-svg" viewBox="0 0 720 230" preserveAspectRatio="none" aria-label="Tendance reelle des indicateurs sur 6 mois">
                        <path className="pl-line-grid" d="M0 45 H720 M0 92 H720 M0 139 H720 M0 186 H720" />
                        <path className="pl-line-green" d={seriesPath(executive.studentsHelpedSeries, 720, 205)} />
                        <path className="pl-line-blue" d={seriesPath(executive.questionsSeries, 720, 205)} />
                        <path className="pl-line-orange" d={seriesPath(executive.signalsSeries, 720, 205)} />
                        <path className="pl-line-violet" d={seriesPath(executive.feedbackSeries, 720, 205)} />
                      </svg>
                      <div className="pl-inst-line-labels">
                        {executive.timeSeries.map((month) => (
                          <span key={month.key}>{month.label}</span>
                        ))}
                      </div>
                      <div className="pl-inst-line-values">
                        {executive.timeSeries.map((month) => (
                          <div key={month.key}>
                            <b>{month.studentsHelped}</b>
                            <b>{month.questionsAnswered}</b>
                            <b>{month.liveSignals}</b>
                            <b>{Math.round(month.feedbackUsefulRate * 100)}%</b>
                          </div>
                        ))}
                      </div>
                    </div>
                  </article>

                  <article className="pl-inst-panel pl-inst-score-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Score moyen par profil</h2>
                    </div>
                    <div className="pl-inst-profile-mini-grid">
                      {metrics.profileAverages.map((profile) => (
                        <div key={profile.key} className="pl-inst-profile-mini">
                          <span>{profile.label}</span>
                          <b>{profile.avg}%</b>
                          <i>
                            <em style={{ width: `${clamp(profile.avg, 0, 100)}%`, background: profile.color }} />
                          </i>
                        </div>
                      ))}
                    </div>
                  </article>

                  <article className="pl-inst-panel pl-inst-teacher-table-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Donnees par professeur</h2>
                      <div className="pl-inst-table-actions">
                        <button type="button" className="pl-inst-mini-select">Toutes les matieres <span className="material-symbols-outlined">expand_more</span></button>
                        <button type="button" className="pl-inst-mini-select">Impact global <span className="material-symbols-outlined">expand_more</span></button>
                      </div>
                    </div>
                    <div className="pl-inst-teacher-table">
                      <div className="pl-inst-teacher-row pl-inst-teacher-row--head">
                        <span>Professeur</span>
                        <span>Groupes</span>
                        <span>Eleves aides</span>
                        <span>Questions</span>
                        <span>Feedback</span>
                        <span>Impact</span>
                        <span>Tendance</span>
                      </div>
                      {!executive.teacherRows.length ? (
                        <div className="pl-inst-table-empty">Aucune donnee professeur disponible.</div>
                      ) : null}
                      {executive.teacherRows.slice(0, 5).map((teacher, index) => {
                        const answerWeight = Math.min(30, teacher.questionsAnswered * 2);
                        const signalPenalty = Math.min(18, teacher.signals);
                        const impact = clamp(58 + teacher.groups * 5 + teacher.studentsHelped * 3 + answerWeight - signalPenalty, 0, 100);
                        return (
                          <div key={teacher.teacherId} className="pl-inst-teacher-row">
                            <span className="pl-inst-teacher-name">
                              <i>{teacher.teacherName.slice(0, 1).toUpperCase()}</i>
                              <b>{teacher.teacherName}</b>
                            </span>
                            <span>{teacher.groups}</span>
                            <span>{teacher.studentsHelped}</span>
                            <span>{teacher.questionsAnswered.toLocaleString("fr-CA")}</span>
                            <span><mark style={{ "--progress": `${clamp(impact - 6, 25, 95)}%` } as React.CSSProperties}>{clamp(impact - 6, 35, 95)}%</mark></span>
                            <span className={`pl-inst-impact-pill ${impact < 65 ? "is-low" : impact < 80 ? "is-mid" : ""}`}>{impact}/100</span>
                            <span className={teacher.signals > teacher.questionsAnswered ? "pl-inst-trend-down" : "pl-inst-trend-up"}>
                              <span className="material-symbols-outlined">{teacher.signals > teacher.questionsAnswered ? "south_east" : "north_east"}</span>
                              {teacher.signals > teacher.questionsAnswered ? "-" : "+"}{Math.max(1, Math.abs(teacher.questionsAnswered - teacher.signals))} pts
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </article>
                </div>

                <aside className="pl-inst-right-stack">
                  <article className="pl-inst-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Profils a prioriser</h2>
                    </div>
                    {executive.riskRows.map(({ label, value, color }) => (
                      <div key={label} className="pl-inst-risk-row">
                        <div>
                          <span>{label}</span>
                          <b>{value}%</b>
                        </div>
                        <div className="pl-inst-risk-track">
                          <i style={{ width: `${clamp(value, 4, 100)}%`, background: color }} />
                        </div>
                      </div>
                    ))}
                  </article>

                  <article className="pl-inst-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Recommandations institutionnelles</h2>
                    </div>
                    <div className="pl-inst-recs-list">
                      {(metrics.topRecommendations.length ? metrics.topRecommendations : [
                        { text: "Renforcer l'accompagnement des eleves a risque eleve.", count: 3 },
                        { text: "Developper des ressources sur la comprehension de texte.", count: 2 },
                        { text: "Encourager les enseignants les moins actifs a utiliser l'IA Tutor.", count: 1 }
                      ]).slice(0, 3).map((rec, index) => (
                        <div key={rec.text} className="pl-inst-rec-item pl-inst-rec-item--exec">
                          <span className={`pl-inst-rec-icon pl-inst-rec-icon--${index + 1}`}>
                            <span className="material-symbols-outlined">{index === 0 ? "group_remove" : index === 1 ? "menu_book" : "monitoring"}</span>
                          </span>
                          <div className="pl-inst-rec-text">
                            <p>{rec.text}</p>
                            <span className={`pl-inst-priority pl-inst-priority--${index + 1}`}>{index === 0 ? "Priorite haute" : index === 1 ? "Priorite moyenne" : "Priorite faible"}</span>
                          </div>
                          <span className="material-symbols-outlined">chevron_right</span>
                        </div>
                      ))}
                    </div>
                    <button type="button" className="pl-inst-detail-btn pl-inst-detail-btn--wide">
                      Voir toutes les recommandations
                      <span className="material-symbols-outlined">chevron_right</span>
                    </button>
                  </article>

                  <article className="pl-inst-panel">
                    <div className="pl-inst-panel-head">
                      <h2>Signaux live - carte thermique</h2>
                    </div>
                    <div className="pl-inst-heatmap">
                      <div className="pl-inst-heatmap-head">
                        <span />
                        {executive.heatmapColumns.map((col) => <b key={col.key}>{col.label}</b>)}
                      </div>
                      {!executive.heatmapRows.length ? (
                        <div className="pl-inst-table-empty">Aucun signal live disponible.</div>
                      ) : null}
                      {executive.heatmapRows.map((row) => (
                        <div key={row.sessionId} className="pl-inst-heatmap-row">
                          <span>{row.label}</span>
                          {executive.heatmapColumns.map((col) => (
                            <i
                              key={`${row.sessionId}-${col.key}`}
                              className={heatClass(row.values[col.key] ?? 0, executive.heatmapMax)}
                              title={`${col.label}: ${row.values[col.key] ?? 0}`}
                            />
                          ))}
                        </div>
                      ))}
                    </div>
                    <div className="pl-inst-heat-legend">
                      <span><i className="heat-0" />Faible</span>
                      <span><i className="heat-2" />Modere</span>
                      <span><i className="heat-3" />Eleve</span>
                      <span><i className="heat-4" />Critique</span>
                    </div>
                    <button type="button" className="pl-inst-detail-btn pl-inst-detail-btn--wide">
                      Voir le detail par classe
                      <span className="material-symbols-outlined">chevron_right</span>
                    </button>
                  </article>
                </aside>
              </section>
            </>
          ) : null}
        </div>
      </div>
    </WpAppShell>
  );
}

