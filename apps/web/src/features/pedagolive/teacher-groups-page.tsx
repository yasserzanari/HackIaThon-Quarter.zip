"use client";

import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { WpAppShell } from "@/features/app-shell/wp-app-shell";
import {
  fetchPedagoLiveCatalog,
  fetchPedagoLiveGroupStudents,
  fetchPedagoLiveSessions
} from "@/features/pedagolive/api-client";
import type { PedagoLiveCourse, PedagoLiveGroup, PedagoLiveGroupStudent, PedagoLiveSession } from "./types";

type CourseView = {
  id: string;
  title: string;
  icon: string;
  groups: number;
  students: number;
};

type GroupView = {
  id: string;
  badge: string;
  badgeTone: "blue" | "purple" | "green";
  name: string;
  level: string;
  courseId?: string;
  courseTitle: string;
  students: number;
  progress: number;
  progressText: string;
  progressTone: "blue" | "purple" | "green";
  date: string;
  slot: string;
  sessions: PedagoLiveSession[];
  raw: PedagoLiveGroup;
};

function toneClass(tone: "blue" | "purple" | "green") {
  if (tone === "blue") return "toneBlue";
  if (tone === "purple") return "tonePurple";
  return "toneGreen";
}

function initials(label: string) {
  const words = label
    .replace(/[^a-zA-Z0-9À-ÿ ]/g, " ")
    .split(/\s+/)
    .filter(Boolean);
  return words.slice(0, 2).map((word) => word[0]?.toUpperCase()).join("") || "GR";
}

function courseIcon(course: PedagoLiveCourse) {
  return (course.code?.slice(0, 3) || initials(course.title).slice(0, 3)).toUpperCase();
}

function formatDateTime(value?: string) {
  if (!value) return { date: "Aucune séance planifiée", slot: "-" };
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return { date: "Aucune séance planifiée", slot: "-" };
  return {
    date: new Intl.DateTimeFormat("fr-CA", { weekday: "long", day: "numeric", month: "long", year: "numeric" }).format(date),
    slot: new Intl.DateTimeFormat("fr-CA", { hour: "2-digit", minute: "2-digit" }).format(date)
  };
}

function progressFromSessions(sessions: PedagoLiveSession[]) {
  if (!sessions.length) return { progress: 0, progressText: "Aucune séance", progressTone: "blue" as const };
  const score = sessions.reduce((total, session) => {
    if (session.status === "finished") return total + 100;
    if (session.status === "live") return total + 78;
    if (session.status === "ready") return total + 45;
    return total + 18;
  }, 0);
  const progress = Math.round(score / sessions.length);
  if (progress >= 70) return { progress, progressText: "En avance", progressTone: "blue" as const };
  if (progress >= 35) return { progress, progressText: "Dans les temps", progressTone: "purple" as const };
  return { progress, progressText: "À lancer", progressTone: "green" as const };
}

export function TeacherPedagoLiveGroupsPage() {
  const searchParams = useSearchParams();
  const initialGroupId = searchParams?.get("groupId") ?? null;
  const initialCourseId = searchParams?.get("courseId") ?? "";
  const initialGroupName = searchParams?.get("groupName") ?? null;
  const [selectedCourseId, setSelectedCourseId] = useState(initialCourseId);
  const [openedGroupId, setOpenedGroupId] = useState<string | null>(initialGroupId);
  const [courses, setCourses] = useState<PedagoLiveCourse[]>([]);
  const [groups, setGroups] = useState<PedagoLiveGroup[]>([]);
  const [sessions, setSessions] = useState<PedagoLiveSession[]>([]);
  const [studentsByGroup, setStudentsByGroup] = useState<Record<string, PedagoLiveGroupStudent[]>>({});
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [groupTab, setGroupTab] = useState<"overview" | "students" | "insights" | "sessions" | "resources">(
    "overview"
  );

  useEffect(() => {
    setSelectedCourseId(initialCourseId);
    setOpenedGroupId(initialGroupId);
  }, [initialCourseId, initialGroupId]);

  useEffect(() => {
    let cancelled = false;
    async function loadRealData() {
      try {
        setLoading(true);
        setLoadError(null);
        const [catalog, allSessions] = await Promise.all([fetchPedagoLiveCatalog(), fetchPedagoLiveSessions()]);
        if (cancelled) return;
        setCourses(catalog.courses);
        setGroups(catalog.groups);
        setSessions(allSessions);
        const entries = await Promise.all(
          catalog.groups.map(async (group) => {
            try {
              return [group.id, await fetchPedagoLiveGroupStudents(group.id)] as const;
            } catch {
              return [group.id, []] as const;
            }
          })
        );
        if (!cancelled) setStudentsByGroup(Object.fromEntries(entries));
      } catch (error) {
        if (!cancelled) setLoadError(error instanceof Error ? error.message : "Impossible de charger les groupes PedagoLive.");
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void loadRealData();
    return () => {
      cancelled = true;
    };
  }, []);

  const courseRows = useMemo<CourseView[]>(() => {
    return courses.map((course) => {
      const courseGroups = groups.filter((group) => group.courseId === course.id);
      const studentIds = new Set(courseGroups.flatMap((group) => (studentsByGroup[group.id] ?? []).map((student) => student.studentId)));
      return { id: course.id, title: course.title, icon: courseIcon(course), groups: courseGroups.length, students: studentIds.size };
    });
  }, [courses, groups, studentsByGroup]);

  const selectedCourse = useMemo(() => courseRows.find((c) => c.id === selectedCourseId) ?? courseRows[0] ?? null, [courseRows, selectedCourseId]);

  useEffect(() => {
    if (!selectedCourseId && courseRows[0]) setSelectedCourseId(courseRows[0].id);
  }, [courseRows, selectedCourseId]);

  const groupRows = useMemo<GroupView[]>(() => {
    const courseById = new Map(courses.map((course) => [course.id, course]));
    return groups.map((group, index) => {
      const groupSessions = sessions.filter((session) => session.groupId === group.id);
      const nextSession = groupSessions
        .filter((session) => session.status !== "finished")
        .sort((a, b) => Date.parse(a.startedAt ?? a.createdAt) - Date.parse(b.startedAt ?? b.createdAt))[0] ?? groupSessions[0];
      const when = formatDateTime(nextSession?.startedAt ?? nextSession?.createdAt);
      const course = group.courseId ? courseById.get(group.courseId) : undefined;
      return {
        id: group.id,
        badge: initials(group.label).slice(0, 3),
        badgeTone: (["blue", "purple", "green"] as const)[index % 3],
        name: group.label,
        level: course?.title ?? "Groupe PedagoLive",
        courseId: group.courseId,
        courseTitle: course?.title ?? "Cours non associé",
        students: studentsByGroup[group.id]?.length ?? 0,
        ...progressFromSessions(groupSessions),
        date: when.date,
        slot: when.slot,
        sessions: groupSessions,
        raw: group
      };
    });
  }, [courses, groups, sessions, studentsByGroup]);

  const visibleGroups = useMemo(() => {
    if (!selectedCourse?.id) return groupRows;
    return groupRows.filter((group) => group.courseId === selectedCourse.id);
  }, [groupRows, selectedCourse?.id]);

  const openedGroup = useMemo(() => {
    const knownGroup = groupRows.find((g) => g.id === openedGroupId);
    if (knownGroup) return knownGroup;
    if (!openedGroupId) return null;
    return {
      id: openedGroupId,
      badge: "GR",
      badgeTone: "blue" as const,
      name: initialGroupName ?? "Groupe du cours",
      level: "Groupe PedagoLive",
      courseTitle: "Cours non associé",
      students: 0,
      progress: 0,
      progressText: "Données réelles en cours de chargement",
      progressTone: "blue" as const,
      date: "Prochaine séance à planifier",
      slot: "-",
      sessions: [],
      raw: { id: openedGroupId, label: initialGroupName ?? "Groupe du cours" }
    };
  }, [groupRows, initialGroupName, openedGroupId]);

  const openedGroupStudents = openedGroup ? studentsByGroup[openedGroup.id] ?? [] : [];
  const openedGroupSessions = openedGroup?.sessions ?? [];
  const finishedSessionCount = openedGroupSessions.filter((session) => session.status === "finished").length;
  const activeOrReadySessionCount = openedGroupSessions.filter(
    (session) => session.status === "live" || session.status === "ready"
  ).length;
  const recentActivities = [
    ...openedGroupSessions.slice(0, 3).map((session) => `Séance "${session.title}" - ${session.status}`),
    ...openedGroupStudents.slice(0, 2).map((student) => `${student.displayName || student.email} invité dans le groupe`)
  ];
  return (
    <WpAppShell active="pedagolive-groups" breadcrumb="Groupes" fullscreen>
      <div className={openedGroup ? "page pageDetail" : "page"}>
        {!openedGroup ? (
          <>
            <header className="header">
              <div>
                <h1>Groupe</h1>
                <p>Gérez vos cours et leurs groupes</p>
              </div>
            </header>

            <section className="layout">
              <aside className="coursesPanel">
                <h2>Mes cours</h2>
                <div className="filters">
                  <div className="search">Rechercher un cours...</div>
                  <div className="status">Tous les statuts</div>
                </div>
                <div className="courseList">
                  {loading ? <div className="emptyState">Chargement des cours...</div> : null}
                  {loadError ? <div className="emptyState error">{loadError}</div> : null}
                  {!loading && !loadError && courseRows.length === 0 ? (
                    <div className="emptyState">Aucun cours PedagoLive trouve pour ce professeur.</div>
                  ) : null}
                  {courseRows.map((course) => (
                    <button
                      key={course.id}
                      type="button"
                      className={`courseCard ${course.id === selectedCourseId ? "selected" : ""}`}
                      onClick={() => setSelectedCourseId(course.id)}
                    >
                      <span className="courseIcon">{course.icon}</span>
                      <span className="courseMeta">
                        <strong>{course.title}</strong>
                        <small>
                          {course.groups} groupes - {course.students} étudiants
                        </small>
                      </span>
                      <span className="activeTag">Actif</span>
                    </button>
                  ))}
                </div>
                <button type="button" className="newCourseBottom">
                  + Nouveau cours
                </button>
              </aside>

              <main className="groupsPanel">
                <div className="groupsTop">
                  <div className="courseHeader">
                    <div className="courseHeaderIcon">{selectedCourse?.icon ?? "PL"}</div>
                    <div>
                      <h3>{selectedCourse?.title ?? "Groupes PedagoLive"}</h3>
                      <p>
                        {selectedCourse?.groups ?? visibleGroups.length} groupes - {selectedCourse?.students ?? 0} étudiants
                      </p>
                    </div>
                  </div>
                  <button type="button" className="newGroupBtn">
                    + Nouveau groupe
                  </button>
                </div>

                <div className="gridHead">
                  <span>Nom du groupe</span>
                  <span>Étudiants</span>
                  <span>Progression</span>
                  <span>Prochaine séance</span>
                  <span>Actions</span>
                </div>

                <div className="rows">
                  {!loading && !loadError && visibleGroups.length === 0 ? (
                    <div className="emptyState">Aucun groupe réel pour ce cours.</div>
                  ) : null}
                  {visibleGroups.map((group) => (
                    <article
                      key={group.id}
                      className="row"
                      role="button"
                      tabIndex={0}
                      onClick={() => setOpenedGroupId(group.id)}
                      onKeyDown={(event) => {
                        if (event.key === "Enter" || event.key === " ") {
                          event.preventDefault();
                          setOpenedGroupId(group.id);
                        }
                      }}
                      aria-label={`Ouvrir ${group.name}`}
                    >
                      <div className="groupCol">
                        <span className={`round ${toneClass(group.badgeTone)}`}>{group.badge}</span>
                        <div>
                          <strong>{group.name}</strong>
                          <small>{group.level}</small>
                        </div>
                      </div>
                      <div className="studentsCol">
                        <strong>{group.students}</strong>
                        <small>Étudiants</small>
                      </div>
                      <div className="progressCol">
                        <strong>{group.progress}%</strong>
                        <div className="track">
                          <div className={`fill ${toneClass(group.progressTone)}`} style={{ width: `${group.progress}%` }} />
                        </div>
                        <small className={toneClass(group.progressTone)}>{group.progressText}</small>
                      </div>
                      <div className="dateCol">
                        <strong>{group.date}</strong>
                        <small>{group.slot}</small>
                      </div>
                      <div className="actionsCol">
                        <button
                          type="button"
                          onClick={(event) => {
                            event.stopPropagation();
                            setOpenedGroupId(group.id);
                          }}
                        >
                          Voir
                        </button>
                        <button type="button" onClick={(event) => event.stopPropagation()}>Modifier</button>
                        <button type="button" onClick={(event) => event.stopPropagation()}>Archiver</button>
                      </div>
                    </article>
                  ))}
                </div>
              </main>
            </section>
          </>
        ) : (
          <>
            <div className="crumbLine">Groupe &gt; {openedGroup.courseTitle} &gt; {openedGroup.name}</div>
            <header className="detailHeader">
              <button type="button" className="topBack" onClick={() => setOpenedGroupId(null)}>
                Retour
              </button>
              <h1>{openedGroup.name}</h1>
              <span className="statePill">Actif</span>
            </header>

            <section className="tabsBar">
              <button type="button" className={groupTab === "overview" ? "tab active" : "tab"} onClick={() => setGroupTab("overview")}>Vue d&apos;ensemble</button>
              <button type="button" className={groupTab === "students" ? "tab active" : "tab"} onClick={() => setGroupTab("students")}>Étudiants</button>
              <button type="button" className={groupTab === "insights" ? "tab active" : "tab"} onClick={() => setGroupTab("insights")}>Difficultés &amp; Insights IA</button>
              <button type="button" className={groupTab === "sessions" ? "tab active" : "tab"} onClick={() => setGroupTab("sessions")}>Séances</button>
              <button type="button" className={groupTab === "resources" ? "tab active" : "tab"} onClick={() => setGroupTab("resources")}>Ressources</button>
            </section>

            {groupTab !== "insights" ? <section className="kpis">
              <article className="kpi">
                <h4>Étudiants actifs</h4>
                <strong>{openedGroupStudents.length} / {openedGroup.students}</strong>
                <small>Étudiants réels rattachés au groupe</small>
              </article>
              <article className="kpi">
                <h4>Progression moyenne</h4>
                <strong>{openedGroup.progress}%</strong>
                <small className="okText">{openedGroup.progressText}</small>
              </article>
              <article className="kpi">
                <h4>Séances cette semaine</h4>
                <strong>{openedGroupSessions.length}</strong>
                <small>{finishedSessionCount} terminée(s)</small>
              </article>
              <article className="kpi">
                <h4>Alertes</h4>
                <strong>{activeOrReadySessionCount}</strong>
                <small className="warnText">Session(s) prête(s) ou en direct</small>
              </article>
            </section> : (
              <section className="insightTopKpis">
                <article className="kpi"><h4>Taux de confusion global</h4><strong>48%</strong><small className="warnText">+8% vs semaine précédente</small></article>
                <article className="kpi"><h4>Questions IA par étudiant</h4><strong>18,7</strong><small className="okText">+3,2 vs semaine précédente</small></article>
                <article className="kpi"><h4>Notions non comprises (7j)</h4><strong>23</strong><small className="warnText">+6 vs semaine précédente</small></article>
                <article className="kpi"><h4>Progression après intervention</h4><strong>+22%</strong><small className="okText">Impact positif moyen</small></article>
                <article className="kpi"><h4>Temps moyen avant aide</h4><strong>9m 42s</strong><small>-1m 15s vs semaine précédente</small></article>
              </section>
            )}

            {groupTab !== "insights" ? <section className="detailLayout">
              <main className="detailMain">
                <article className="panel">
                  <h3>Vue d&apos;ensemble</h3>
                  <p>
                    Groupe réel rattaché au cours {openedGroup.courseTitle}. Cette vue consolide les étudiants invités,
                    les séances PedagoLive et l&apos;état de progression calculé depuis les sessions existantes.
                  </p>
                </article>

                <article className="panel">
                  <h3>Progression des étudiants</h3>
                  <div className="table">
                    <div className="thead">
                      <span>Nom</span>
                      <span>Avancement</span>
                      <span>Dernière activité</span>
                      <span>Statut</span>
                    </div>
                    {openedGroupStudents.length ? (
                      openedGroupStudents.map((student) => (
                        <div className="trow" key={student.membershipId}>
                          <span>{student.displayName || student.email}</span>
                          <span>{openedGroup.progress}%</span>
                          <span>{student.invitedAt ? formatDateTime(student.invitedAt).date : "Invitation active"}</span>
                          <span className="chip good">Invité</span>
                        </div>
                      ))
                    ) : (
                      <div className="trow emptyRow">
                        <span>Aucun étudiant réel dans ce groupe</span>
                        <span>-</span>
                        <span>-</span>
                        <span className="chip mid">Vide</span>
                      </div>
                    )}
                  </div>
                </article>

                <article className="panel">
                  <h3>Prochaines séances</h3>
                  <ul className="timeline">
                    {openedGroupSessions.length ? (
                      openedGroupSessions.slice(0, 4).map((session) => {
                        const when = formatDateTime(session.startedAt ?? session.createdAt);
                        return (
                          <li key={session.id}>
                            <strong>{when.date}</strong>
                            <span>{when.slot}</span>
                            <em>{session.title} - {session.status}</em>
                          </li>
                        );
                      })
                    ) : (
                      <li><strong>Aucune séance</strong><span>-</span><em>Planifiez une séance pour ce groupe</em></li>
                    )}
                  </ul>
                </article>

                <article className="panel">
                  <h3>Ressources du groupe</h3>
                  <div className="resources">
                    <div className="res">Cours parent<br />{openedGroup.courseTitle}</div>
                    <div className="res">Groupe<br />{openedGroup.name}</div>
                    <div className="res">Étudiants invités<br />{openedGroup.students}</div>
                    <div className="res">Séances liées<br />{openedGroupSessions.length}</div>
                  </div>
                </article>
              </main>

              <aside className="detailSide">
                <article className="panel">
                  <h3>Actions du groupe</h3>
                  <div className="stack">
                    <button type="button">Modifier le groupe</button>
                    <button type="button">Planifier une séance</button>
                    <button type="button">Exporter rapport</button>
                    <button type="button" className="danger">Archiver le groupe</button>
                  </div>
                </article>
                <article className="panel">
                  <h3>Infos rapides</h3>
                  <ul className="infos">
                    <li><span>Cours parent</span><strong>{openedGroup.courseTitle}</strong></li>
                    <li><span>Nombre d&apos;étudiants</span><strong>{openedGroup.students}</strong></li>
                    <li><span>Code groupe</span><strong>{openedGroup.id}</strong></li>
                    <li><span>Mode</span><strong>En ligne</strong></li>
                  </ul>
                </article>
                <article className="panel">
                  <h3>Activité récente</h3>
                  <ul className="activity">
                    {recentActivities.length ? (
                      recentActivities.map((activity) => <li key={activity}>{activity}</li>)
                    ) : (
                      <li>Aucune activité réelle récente pour ce groupe.</li>
                    )}
                  </ul>
                </article>
              </aside>
            </section> : (
              <section className="insightsLayout">
                <main className="insightsMain">
                  <article className="panel">
                    <div className="insightDual">
                      <div>
                        <h3>Matières les moins bien comprises</h3>
                        <ul className="bars red">
                          <li><span>Applications linéaires</span><em style={{ width: "72%" }} /></li>
                          <li><span>Espaces vectoriels</span><em style={{ width: "61%" }} /></li>
                          <li><span>Valeurs propres</span><em style={{ width: "54%" }} /></li>
                          <li><span>Diagonalisation</span><em style={{ width: "48%" }} /></li>
                          <li><span>Changements de base</span><em style={{ width: "41%" }} /></li>
                        </ul>
                        <h3 style={{ marginTop: 14 }}>Matières les mieux comprises</h3>
                        <ul className="bars green">
                          <li><span>Systèmes linéaires</span><em style={{ width: "86%" }} /></li>
                          <li><span>Calcul matriciel</span><em style={{ width: "81%" }} /></li>
                          <li><span>Déterminants</span><em style={{ width: "75%" }} /></li>
                          <li><span>Rang d&apos;une matrice</span><em style={{ width: "68%" }} /></li>
                          <li><span>Produit scalaire</span><em style={{ width: "64%" }} /></li>
                        </ul>
                      </div>
                      <div>
                        <h3>Évolution des difficultés par séance</h3>
                        <div className="lineMock">
                          <div className="line"></div>
                          <div className="dots">
                            <span style={{ left: "8%", top: "28%" }}>62%</span>
                            <span style={{ left: "23%", top: "34%" }}>58%</span>
                            <span style={{ left: "38%", top: "26%" }}>64%</span>
                            <span style={{ left: "53%", top: "37%" }}>55%</span>
                            <span style={{ left: "68%", top: "42%" }}>49%</span>
                            <span style={{ left: "83%", top: "46%" }}>46%</span>
                            <span style={{ left: "95%", top: "50%" }}>42%</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </article>

                  <article className="panel">
                    <h3>Clusters d&apos;élèves</h3>
                    <div className="clusters">
                      <div className="cluster red"><strong>Groupe A - Surcharge cognitive</strong><p>11 élèves (39%)</p><p>Quoi revoir avec eux: décomposer par étape, renforcer les prérequis.</p></div>
                      <div className="cluster orange"><strong>Groupe B - Vocabulaire disciplinaire</strong><p>9 élèves (32%)</p><p>Quoi revoir: lexique, reformulation, cartes conceptuelles.</p></div>
                      <div className="cluster green"><strong>Groupe C - Rythme / Attention</strong><p>8 élèves (29%)</p><p>Quoi revoir: défis, démonstrations, projets d&apos;application.</p></div>
                    </div>
                  </article>

                  <article className="panel">
                    <h3>Tableau de suivi élève</h3>
                    <div className="table">
                      <div className="thead">
                        <span>Élève</span><span>Signaux PedagoLive</span><span>Demandes Assistant IA</span><span>Notions bloquantes</span><span>Notions maîtrisées</span><span>Recommandation prochaine séance</span>
                      </div>
                      {openedGroupStudents.length ? (
                        openedGroupStudents.slice(0, 5).map((student) => (
                          <div className="trow" key={student.membershipId}>
                            <span>{student.displayName || student.email}</span>
                            <span>{openedGroup.progress >= 60 ? "stable" : "à suivre"}</span>
                            <span>Données IA à consolider</span>
                            <span>{openedGroupSessions.length ? "Voir séances" : "-"}</span>
                            <span>{openedGroup.courseTitle}</span>
                            <span>{openedGroupSessions.length ? "Analyser la prochaine séance" : "Planifier une séance"}</span>
                          </div>
                        ))
                      ) : (
                        <div className="trow emptyRow">
                          <span>Aucun étudiant réel</span><span>-</span><span>-</span><span>-</span><span>-</span><span>Inviter des étudiants</span>
                        </div>
                      )}
                    </div>
                  </article>
                </main>

                <aside className="insightsSide">
                  <article className="panel">
                    <h3>Plan de reprise</h3>
                    <ul className="activity">
                      <li>1. Concepts à revoir en priorité</li>
                      <li>2. Mini-évaluation ciblée</li>
                      <li>3. Pairing d&apos;élèves</li>
                      <li>4. Ressources conseillées</li>
                    </ul>
                  </article>
                </aside>
              </section>
            )}
          </>
        )}
      </div>

      <style jsx>{`
        :global(html),
        :global(body) {
          background: #f6f8fd;
        }
        :global(.pl-app-layout.pl-app-layout--workbench) {
          height: 100vh !important;
          background: #f6f8fd;
        }
        :global(.pl-app-main.pl-app-main--workbench) {
          height: 100vh !important;
          padding-top: 4rem !important;
          background: #f6f8fd;
        }
        .page {
          width: 100%;
          height: 100%;
          min-height: 0;
          overflow-y: auto;
          overflow-x: hidden;
          background: #f6f8fd;
          padding: 18px 18px 32px;
          color: #122455;
          font-size: 14px;
          scrollbar-gutter: stable;
        }
        .pageDetail {
          padding-bottom: 36px;
        }
        .header h1,
        .detailHeader h1 {
          margin: 0;
          font-size: 34px;
          line-height: 1.05;
          font-weight: 700;
          letter-spacing: -0.015em;
        }
        .header p {
          margin: 6px 0 0;
          font-size: 15px;
          color: #6f7ea6;
          font-weight: 500;
        }
        .layout {
          margin-top: 12px;
          display: grid;
          grid-template-columns: 360px 1fr;
          gap: 12px;
        }
        .coursesPanel,
        .groupsPanel,
        .panel,
        .kpi {
          background: #fff;
          border: 1px solid #e3e9f6;
          border-radius: 14px;
          box-shadow: 0 10px 22px rgba(23, 45, 95, 0.05);
        }
        .coursesPanel {
          padding: 14px;
        }
        .coursesPanel h2 {
          margin: 0 0 10px;
          font-size: 38px;
          line-height: 1.1;
        }
        .filters {
          display: grid;
          grid-template-columns: 1fr 142px;
          gap: 8px;
          margin-bottom: 10px;
        }
        .search,
        .status {
          height: 38px;
          border: 1px solid #dce3f2;
          background: #fbfcff;
          border-radius: 9px;
          padding: 10px 12px;
          color: #9aa7c6;
          font-size: 13px;
        }
        .courseList {
          display: grid;
          gap: 9px;
        }
        .emptyState {
          border: 1px dashed #cfd9ee;
          border-radius: 12px;
          background: #f9fbff;
          color: #51638f;
          padding: 16px;
          font-size: 13px;
          font-weight: 700;
          text-align: center;
        }
        .emptyState.error {
          border-color: #fecaca;
          background: #fff7f7;
          color: #dc2626;
        }
        .courseCard {
          border: 1px solid #e3e8f4;
          border-radius: 11px;
          background: #fff;
          min-height: 72px;
          display: grid;
          grid-template-columns: 40px 1fr auto;
          gap: 10px;
          align-items: center;
          padding: 9px 10px;
          text-align: left;
        }
        .courseCard.selected {
          border-color: #5e88ff;
          box-shadow: 0 0 0 2px rgba(88, 133, 255, 0.16);
        }
        .courseIcon {
          width: 40px;
          height: 40px;
          border-radius: 9px;
          display: grid;
          place-items: center;
          background: #edf2ff;
          color: #3969ff;
          font-weight: 800;
        }
        .courseMeta strong {
          display: block;
          font-size: 16px;
          color: #17295c;
          line-height: 1.2;
        }
        .courseMeta small {
          font-size: 13px;
          color: #7b89ae;
        }
        .activeTag {
          font-size: 12px;
          font-weight: 700;
          color: #0ea66f;
          background: #e9faf2;
          border-radius: 999px;
          padding: 5px 9px;
        }
        .newCourseBottom {
          width: 100%;
          margin-top: 12px;
          height: 48px;
          border: 1px solid #d8e1f7;
          background: #eef3ff;
          color: #1f5df0;
          border-radius: 11px;
          font-size: 18px;
          font-weight: 700;
        }
        .groupsPanel {
          padding: 14px;
        }
        .groupsTop {
          display: flex;
          align-items: center;
          justify-content: space-between;
          border-bottom: 1px solid #e6ebf6;
          padding-bottom: 12px;
          margin-bottom: 8px;
        }
        .courseHeader {
          display: flex;
          align-items: center;
          gap: 12px;
        }
        .courseHeaderIcon {
          width: 50px;
          height: 50px;
          border-radius: 10px;
          background: #edf2ff;
          color: #2f64f8;
          display: grid;
          place-items: center;
          font-weight: 800;
          font-size: 22px;
        }
        .courseHeader h3 {
          margin: 0;
          font-size: 38px;
          line-height: 1.1;
        }
        .courseHeader p {
          margin: 4px 0 0;
          color: #7a87ac;
          font-size: 17px;
        }
        .newGroupBtn {
          border: none;
          background: linear-gradient(180deg, #2c6dfb, #1f5de9);
          color: #fff;
          border-radius: 10px;
          font-size: 14px;
          font-weight: 700;
          padding: 12px 18px;
          box-shadow: 0 6px 16px rgba(39, 97, 237, 0.28);
        }
        .gridHead {
          display: grid;
          grid-template-columns: 1.7fr 0.8fr 1fr 1.2fr 1fr;
          padding: 10px 8px;
          color: #243669;
          font-size: 12px;
          font-weight: 700;
        }
        .rows {
          display: grid;
          gap: 10px;
        }
        .row {
          border: 1px solid #e3e8f4;
          border-radius: 12px;
          background: #fff;
          display: grid;
          grid-template-columns: 1.7fr 0.8fr 1fr 1.2fr 1fr;
          align-items: center;
          gap: 8px;
          padding: 12px 12px;
          cursor: pointer;
          transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
        }
        .row:hover,
        .row:focus-visible {
          border-color: #9bb6ff;
          box-shadow: 0 12px 28px rgba(31, 93, 233, 0.12);
          outline: none;
          transform: translateY(-1px);
        }
        .groupCol {
          display: flex;
          gap: 12px;
          align-items: center;
        }
        .groupCol strong {
          display: block;
          font-size: 23px;
          line-height: 1.1;
          color: #182a5c;
        }
        .groupCol small {
          font-size: 12px;
          color: #7d89ad;
        }
        .round {
          width: 56px;
          height: 56px;
          border-radius: 999px;
          display: grid;
          place-items: center;
          font-size: 16px;
          font-weight: 800;
        }
        .studentsCol strong,
        .progressCol strong,
        .dateCol strong {
          display: block;
          font-size: 16px;
          color: #1a2b5e;
          line-height: 1.05;
          font-weight: 700;
        }
        .studentsCol small,
        .progressCol small,
        .dateCol small {
          font-size: 11px;
          color: #7d89ad;
        }
        .track {
          height: 8px;
          border-radius: 999px;
          background: #ecf0f9;
          margin-top: 7px;
          width: 86%;
          overflow: hidden;
        }
        .fill {
          height: 100%;
        }
        .actionsCol {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 8px;
        }
        .actionsCol button {
          min-height: 40px;
          border-radius: 10px;
          border: 1px solid #dde3f2;
          background: #fff;
          color: #273a6d;
          font-size: 12px;
          font-weight: 600;
        }
        .toneBlue {
          background: #edf2ff;
          color: #2f63f8;
        }
        .tonePurple {
          background: #f2ebff;
          color: #7a4fe0;
        }
        .toneGreen {
          background: #e8f9ef;
          color: #17a56f;
        }

        .crumbLine {
          color: #69779f;
          font-size: 13px;
          font-weight: 600;
          margin-bottom: 10px;
        }
        .detailHeader {
          display: flex;
          align-items: center;
          gap: 12px;
          margin-bottom: 12px;
        }
        .topBack {
          border: 1px solid #d8e2f4;
          background: #fff;
          color: #1f356f;
          border-radius: 10px;
          padding: 9px 12px;
          font-size: 13px;
          font-weight: 700;
          cursor: pointer;
        }
        .topBack:hover {
          border-color: #9bb6ff;
          color: #1f5de9;
        }
        .statePill {
          font-size: 13px;
          font-weight: 700;
          color: #2f63f8;
          background: #edf2ff;
          border-radius: 10px;
          padding: 6px 10px;
        }
        .kpis {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 10px;
          margin-bottom: 12px;
        }
        .tabsBar {
          display: flex;
          gap: 20px;
          margin-bottom: 12px;
          border-bottom: 1px solid #e5eaf6;
        }
        .tab {
          border: none;
          background: transparent;
          color: #596a99;
          font-size: 14px;
          font-weight: 600;
          padding: 6px 0 10px;
        }
        .tab.active {
          color: #1f5de9;
          border-bottom: 2px solid #1f5de9;
        }
        .insightTopKpis {
          display: grid;
          grid-template-columns: repeat(5, 1fr);
          gap: 10px;
          margin-bottom: 12px;
        }
        .kpi {
          padding: 14px;
        }
        .kpi h4 {
          margin: 0;
          font-size: 12px;
          color: #6f7ca2;
        }
        .kpi strong {
          display: block;
          margin-top: 8px;
          font-size: 22px;
          color: #10214f;
        }
        .kpi small {
          font-size: 12px;
          color: #6f7ca2;
        }
        .okText {
          color: #1ea76e !important;
          font-weight: 600;
        }
        .warnText {
          color: #ef4444 !important;
          font-weight: 700;
        }
        .detailLayout {
          display: grid;
          grid-template-columns: 1fr 360px;
          gap: 12px;
        }
        .detailMain,
        .detailSide {
          display: grid;
          gap: 10px;
        }
        .panel {
          padding: 14px;
        }
        .panel h3 {
          margin: 0 0 8px;
          font-size: 22px;
          color: #14275a;
        }
        .panel p {
          margin: 0;
          font-size: 13px;
          color: #61719f;
          line-height: 1.5;
        }
        .table {
          border: 1px solid #e7ecf7;
          border-radius: 10px;
          overflow: hidden;
        }
        .thead,
        .trow {
          display: grid;
          grid-template-columns: 1.4fr 0.8fr 1fr 0.8fr;
          align-items: center;
          padding: 10px 12px;
        }
        .thead {
          background: #f9fbff;
          color: #20376d;
          font-size: 12px;
          font-weight: 700;
        }
        .trow {
          border-top: 1px solid #edf1f9;
          color: #1b2d62;
          font-size: 12px;
        }
        .emptyRow {
          color: #6f7ca2;
        }
        .chip {
          border-radius: 999px;
          padding: 3px 9px;
          font-size: 11px;
          font-weight: 700;
        }
        .chip.ok {
          background: #e8f9ef;
          color: #10a56f;
        }
        .chip.good {
          background: #e9f0ff;
          color: #2f63f8;
        }
        .chip.mid {
          background: #fff4e5;
          color: #f08a24;
        }
        .chip.bad {
          background: #ffe9e9;
          color: #ef4444;
        }
        .chip.risk {
          background: #ffe4e6;
          color: #e11d48;
        }
        .timeline {
          list-style: none;
          margin: 0;
          padding: 0;
          display: grid;
          gap: 8px;
        }
        .timeline li {
          display: grid;
          grid-template-columns: 1.2fr 0.8fr 1fr;
          border-top: 1px solid #edf1f9;
          padding-top: 8px;
        }
        .timeline li:first-child {
          border-top: none;
          padding-top: 0;
        }
        .timeline strong {
          font-size: 12px;
          color: #22366b;
        }
        .timeline span,
        .timeline em {
          font-size: 12px;
          color: #5f6f9d;
          font-style: normal;
        }
        .resources {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 8px;
        }
        .res {
          border: 1px solid #e3e9f6;
          border-radius: 10px;
          background: #fcfdff;
          padding: 10px;
          font-size: 12px;
          color: #23386d;
          line-height: 1.4;
        }
        .stack {
          display: grid;
          gap: 7px;
        }
        .stack button {
          min-height: 38px;
          border: 1px solid #dbe3f4;
          border-radius: 10px;
          background: #fff;
          color: #23386d;
          text-align: left;
          padding: 0 12px;
          font-size: 12px;
          font-weight: 600;
        }
        .stack button.danger {
          color: #ef4444;
        }
        .infos,
        .activity {
          list-style: none;
          margin: 0;
          padding: 0;
          display: grid;
          gap: 7px;
        }
        .infos li {
          display: flex;
          justify-content: space-between;
          border-top: 1px solid #edf1f9;
          padding-top: 7px;
        }
        .infos li:first-child {
          border-top: none;
          padding-top: 0;
        }
        .infos span {
          font-size: 12px;
          color: #6d7aa3;
        }
        .infos strong {
          font-size: 12px;
          color: #22366b;
        }
        .activity li {
          border-top: 1px solid #edf1f9;
          padding-top: 7px;
          font-size: 12px;
          color: #2a3f71;
        }
        .activity li:first-child {
          border-top: none;
          padding-top: 0;
        }
        .insightsLayout {
          display: grid;
          grid-template-columns: 1fr 370px;
          gap: 12px;
        }
        .insightsLayout .thead,
        .insightsLayout .trow {
          grid-template-columns: 1fr 0.8fr 1fr 1fr 1fr 1.2fr;
        }
        .insightsMain {
          display: grid;
          gap: 10px;
        }
        .insightsSide {
          display: grid;
          gap: 10px;
          align-content: start;
        }
        .insightDual {
          display: grid;
          grid-template-columns: 360px 1fr;
          gap: 14px;
        }
        .bars {
          list-style: none;
          margin: 8px 0 0;
          padding: 0;
          display: grid;
          gap: 8px;
        }
        .bars li {
          display: grid;
          grid-template-columns: 1fr 180px;
          gap: 10px;
          align-items: center;
          font-size: 12px;
          color: #2a3f71;
        }
        .bars em {
          display: block;
          height: 6px;
          border-radius: 999px;
          background: #cbd5e8;
          position: relative;
          overflow: hidden;
        }
        .bars.red em::after, .bars.green em::after {
          content: "";
          position: absolute;
          left: 0; top: 0; bottom: 0;
          width: inherit;
          background: currentColor;
        }
        .bars.red { color: #ef4444; }
        .bars.green { color: #14a66e; }
        .lineMock {
          height: 220px;
          border: 1px solid #e7ecf7;
          border-radius: 10px;
          background: linear-gradient(to bottom, #fff 0%, #fff 84%, #f8faff 84%, #f8faff 100%);
          position: relative;
        }
        .line {
          position: absolute;
          left: 8%;
          right: 5%;
          top: 28%;
          height: 2px;
          background: #ef4444;
          transform: skewY(-7deg);
          transform-origin: left center;
        }
        .dots span {
          position: absolute;
          font-size: 11px;
          color: #5d6fa1;
        }
        .clusters {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 10px;
        }
        .cluster {
          border: 1px solid #e7ecf7;
          border-radius: 10px;
          padding: 10px;
          font-size: 12px;
          line-height: 1.4;
        }
        .cluster strong {
          display: block;
          font-size: 14px;
          margin-bottom: 4px;
        }
        .cluster.red { background: #fff7f7; }
        .cluster.orange { background: #fffbf5; }
        .cluster.green { background: #f5fff9; }
      `}</style>
    </WpAppShell>
  );
}

