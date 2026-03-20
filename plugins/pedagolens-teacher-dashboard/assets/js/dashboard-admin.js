/**
 * PédagoLens Teacher Dashboard — Front JS v2
 * Counter animations, IntersectionObserver, AJAX analyse/projet,
 * enhanced hover effects, staggered entries, smooth transitions
 */
( function () {
    'use strict';

    /* =====================================================================
       Helpers
       ===================================================================== */

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    function getAjaxConfig() {
        if ( typeof plFront !== 'undefined' ) {
            return {
                url:   plFront.ajaxUrl,
                nonce: plFront.nonces?.dashboard || '',
                i18n:  plFront.i18n || {},
            };
        }
        if ( typeof plDashboard !== 'undefined' ) {
            return {
                url:   plDashboard.ajaxUrl,
                nonce: plDashboard.nonce,
                i18n:  plDashboard.i18n || {},
            };
        }
        return null;
    }

    /* =====================================================================
       Counter Animation — eased with overshoot
       ===================================================================== */

    function animateCounter( el ) {
        const target = parseInt( el.dataset.target, 10 ) || 0;
        if ( target === 0 ) { el.textContent = '0'; return; }

        const duration = 1600;
        const start    = performance.now();

        function step( now ) {
            const elapsed  = now - start;
            const progress = Math.min( elapsed / duration, 1 );
            // ease-out expo for snappy feel
            const ease = progress === 1 ? 1 : 1 - Math.pow( 2, -10 * progress );
            el.textContent = Math.round( ease * target );
            if ( progress < 1 ) requestAnimationFrame( step );
        }

        requestAnimationFrame( step );
    }

    /* =====================================================================
       Tilt Effect — subtle 3D on stat cards
       ===================================================================== */

    function initTiltEffect() {
        const cards = document.querySelectorAll( '.pl-stat-card' );
        cards.forEach( card => {
            card.addEventListener( 'mousemove', ( e ) => {
                const rect = card.getBoundingClientRect();
                const x = ( e.clientX - rect.left ) / rect.width - 0.5;
                const y = ( e.clientY - rect.top ) / rect.height - 0.5;
                card.style.transform = `translateY(-4px) perspective(600px) rotateX(${ -y * 6 }deg) rotateY(${ x * 6 }deg)`;
            } );
            card.addEventListener( 'mouseleave', () => {
                card.style.transform = '';
            } );
        } );
    }

    /* =====================================================================
       IntersectionObserver — fade-in + counters + bars
       ===================================================================== */

    function initObservers() {
        // Animate-in elements
        const animateEls = document.querySelectorAll( '.pl-animate-in' );
        if ( animateEls.length ) {
            const obs = new IntersectionObserver( ( entries ) => {
                entries.forEach( entry => {
                    if ( entry.isIntersecting ) {
                        entry.target.classList.add( 'pl-visible' );
                        obs.unobserve( entry.target );
                    }
                } );
            }, { threshold: 0.08 } );

            animateEls.forEach( el => obs.observe( el ) );
        }

        // Counter elements
        const counters = document.querySelectorAll( '.pl-stat-number[data-target]' );
        if ( counters.length ) {
            const cObs = new IntersectionObserver( ( entries ) => {
                entries.forEach( entry => {
                    if ( entry.isIntersecting ) {
                        // Stagger counter start based on card index
                        const card = entry.target.closest( '.pl-stat-card' );
                        const idx = card ? Array.from( card.parentNode.children ).indexOf( card ) : 0;
                        setTimeout( () => animateCounter( entry.target ), idx * 120 );
                        cObs.unobserve( entry.target );
                    }
                } );
            }, { threshold: 0.3 } );

            counters.forEach( el => cObs.observe( el ) );
        }

        // Score bars — animate width
        animateScoreBars( document );
    }

    function animateScoreBars( container ) {
        const bars = container.querySelectorAll( '.pl-score-bar[data-score]' );
        if ( ! bars.length ) return;

        const bObs = new IntersectionObserver( ( entries ) => {
            entries.forEach( entry => {
                if ( entry.isIntersecting ) {
                    const bar   = entry.target;
                    const score = parseInt( bar.dataset.score, 10 ) || 0;
                    const row   = bar.closest( '.pl-score-row' );
                    const idx   = row ? Array.from( row.parentNode.children ).indexOf( row ) : 0;
                    // Stagger bar animations
                    setTimeout( () => { bar.style.width = score + '%'; }, 150 + idx * 100 );
                    bObs.unobserve( bar );
                }
            } );
        }, { threshold: 0.1 } );

        bars.forEach( el => bObs.observe( el ) );
    }

    /* =====================================================================
       AJAX — Analyze Course
       ===================================================================== */

    function initAnalyzeButtons() {
        document.addEventListener( 'click', function ( e ) {
            const btn = e.target.closest( '.pl-btn-analyze-front' ) || e.target.closest( '.pl-btn-analyze' );
            if ( ! btn ) return;

            const cfg = getAjaxConfig();
            if ( ! cfg ) return;

            const courseId = btn.dataset.courseId;
            const resultEl = document.getElementById( 'pl-analysis-result-' + courseId )
                          || document.getElementById( 'pl-analysis-' + courseId );
            if ( ! resultEl ) return;

            btn.disabled = true;
            const origHTML = btn.innerHTML;
            btn.innerHTML = '<span class="pl-btn-spinner"></span> ' + ( cfg.i18n.analyzing || 'Analyse…' );
            btn.classList.add( 'pl-btn-loading' );

            resultEl.innerHTML = '<div class="pl-loading-pulse">' + ( cfg.i18n.analyzing || 'Analyse en cours…' ) + '</div>';

            const fd = new FormData();
            fd.append( 'action', 'pl_analyze_course' );
            fd.append( 'nonce', cfg.nonce );
            fd.append( 'course_id', courseId );

            fetch( cfg.url, { method: 'POST', body: fd, credentials: 'same-origin' } )
                .then( r => r.json() )
                .then( res => {
                    if ( res.success && res.data?.html ) {
                        // Fade in the result
                        resultEl.style.opacity = '0';
                        resultEl.innerHTML = res.data.html;
                        requestAnimationFrame( () => {
                            resultEl.style.transition = 'opacity .5s ease';
                            resultEl.style.opacity = '1';
                        } );
                        // Re-animate new bars
                        animateScoreBars( resultEl );
                    } else {
                        const msg = res.data?.message || cfg.i18n.analyzeError || 'Erreur.';
                        resultEl.innerHTML = '<div class="pl-notice pl-notice-error"><p>' + escHtml( msg ) + '</p></div>';
                    }
                } )
                .catch( () => {
                    resultEl.innerHTML = '<div class="pl-notice pl-notice-error"><p>' + escHtml( cfg.i18n.analyzeError || 'Erreur réseau.' ) + '</p></div>';
                } )
                .finally( () => {
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                    btn.classList.remove( 'pl-btn-loading' );
                } );
        } );
    }

    /* =====================================================================
       AJAX — Create Project (modal)
       ===================================================================== */

    function initProjectModal() {
        // Open modal
        document.addEventListener( 'click', function ( e ) {
            const btn = e.target.closest( '.pl-btn-create-project' ) || e.target.closest( '.pl-btn-new-project' );
            if ( ! btn ) return;

            const courseId    = btn.dataset.courseId;
            const courseTitle = btn.dataset.courseTitle || '';

            // Remove existing modal
            const existing = document.getElementById( 'pl-project-modal' );
            if ( existing ) existing.remove();

            const modal = document.createElement( 'div' );
            modal.id = 'pl-project-modal';
            modal.className = 'pl-modal-overlay';
            modal.innerHTML = `
                <div class="pl-modal-box">
                    <button type="button" class="pl-modal-close" id="pl-modal-close-btn" aria-label="Fermer">&times;</button>
                    <h2>Nouveau projet — ${ escHtml( courseTitle ) }</h2>
                    <label for="pl-project-title">Titre du projet</label>
                    <input type="text" id="pl-project-title" placeholder="Ex. Analyse du plan de cours" autocomplete="off">
                    <label for="pl-project-type">Type</label>
                    <select id="pl-project-type">
                        <option value="magistral">Magistral (diapositives, plan de cours)</option>
                        <option value="exercice">Exercice (consigne, TP)</option>
                        <option value="evaluation">Évaluation (examen, dissertation)</option>
                        <option value="travail_equipe">Travail d'équipe</option>
                    </select>
                    <div class="pl-modal-actions">
                        <button type="button" class="pl-btn-ghost" id="pl-project-cancel">Annuler</button>
                        <button type="button" class="pl-btn-glow" id="pl-project-create" data-course-id="${ courseId }">Créer le projet</button>
                    </div>
                    <p class="pl-modal-error" id="pl-project-error"></p>
                </div>`;

            document.body.appendChild( modal );

            // Focus with slight delay for animation
            setTimeout( () => {
                document.getElementById( 'pl-project-title' )?.focus();
            }, 100 );
        } );

        // Cancel / close button
        document.addEventListener( 'click', function ( e ) {
            if ( e.target.id === 'pl-project-cancel' || e.target.id === 'pl-modal-close-btn' ) {
                closeModal();
            }
        } );

        // Close on overlay click
        document.addEventListener( 'click', function ( e ) {
            if ( e.target.id === 'pl-project-modal' ) {
                closeModal();
            }
        } );

        // Create
        document.addEventListener( 'click', function ( e ) {
            if ( e.target.id !== 'pl-project-create' ) return;

            const cfg = getAjaxConfig();
            if ( ! cfg ) return;

            const btn      = e.target;
            const courseId  = btn.dataset.courseId;
            const title    = document.getElementById( 'pl-project-title' ).value.trim();
            const type     = document.getElementById( 'pl-project-type' ).value;
            const errorEl  = document.getElementById( 'pl-project-error' );

            if ( ! title ) {
                errorEl.textContent = 'Le titre est requis.';
                errorEl.style.display = 'block';
                // Shake the input
                const input = document.getElementById( 'pl-project-title' );
                input.style.animation = 'pl-shake .4s ease';
                setTimeout( () => { input.style.animation = ''; }, 400 );
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="pl-btn-spinner"></span> Création…';

            const fd = new FormData();
            fd.append( 'action', 'pl_create_project' );
            fd.append( 'nonce', cfg.nonce );
            fd.append( 'course_id', courseId );
            fd.append( 'type', type );
            fd.append( 'title', title );

            fetch( cfg.url, { method: 'POST', body: fd, credentials: 'same-origin' } )
                .then( r => r.json() )
                .then( res => {
                    if ( res.success ) {
                        closeModal();
                        window.location.href = res.data.workbench_url;
                    } else {
                        errorEl.textContent = res.data?.message || 'Erreur.';
                        errorEl.style.display = 'block';
                        btn.disabled = false;
                        btn.textContent = 'Créer le projet';
                    }
                } )
                .catch( () => {
                    errorEl.textContent = 'Erreur réseau.';
                    errorEl.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Créer le projet';
                } );
        } );

        // Enter key to submit
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' && e.target.id === 'pl-project-title' ) {
                const createBtn = document.getElementById( 'pl-project-create' );
                if ( createBtn && ! createBtn.disabled ) createBtn.click();
            }
        } );

        // Escape key
        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) closeModal();
        } );
    }

    function closeModal() {
        const m = document.getElementById( 'pl-project-modal' );
        if ( ! m ) return;
        m.style.opacity = '0';
        m.style.transition = 'opacity .2s ease';
        setTimeout( () => m.remove(), 200 );
    }

    /* =====================================================================
       Ripple Effect on Buttons
       ===================================================================== */

    function initRipple() {
        document.addEventListener( 'click', function ( e ) {
            const btn = e.target.closest( '.pl-btn-glow, .pl-btn-ghost' );
            if ( ! btn ) return;

            const ripple = document.createElement( 'span' );
            const rect = btn.getBoundingClientRect();
            const size = Math.max( rect.width, rect.height );
            ripple.style.cssText = `
                position:absolute; border-radius:50%; pointer-events:none;
                width:${size}px; height:${size}px;
                left:${e.clientX - rect.left - size/2}px;
                top:${e.clientY - rect.top - size/2}px;
                background:rgba(255,255,255,.15);
                transform:scale(0); opacity:1;
                animation: pl-ripple-anim .5s ease-out forwards;
            `;
            btn.style.position = 'relative';
            btn.style.overflow = 'hidden';
            btn.appendChild( ripple );
            setTimeout( () => ripple.remove(), 500 );
        } );

        // Inject ripple keyframes once
        if ( ! document.getElementById( 'pl-ripple-style' ) ) {
            const style = document.createElement( 'style' );
            style.id = 'pl-ripple-style';
            style.textContent = `
                @keyframes pl-ripple-anim {
                    to { transform: scale(2.5); opacity: 0; }
                }
                @keyframes pl-shake {
                    0%, 100% { transform: translateX(0); }
                    20% { transform: translateX(-6px); }
                    40% { transform: translateX(6px); }
                    60% { transform: translateX(-4px); }
                    80% { transform: translateX(4px); }
                }
                .pl-btn-spinner {
                    display: inline-block;
                    width: 12px; height: 12px;
                    border: 2px solid rgba(255,255,255,.3);
                    border-top-color: #fff;
                    border-radius: 50%;
                    animation: pl-spin .6s linear infinite;
                    vertical-align: middle;
                }
                @keyframes pl-spin {
                    to { transform: rotate(360deg); }
                }
                .pl-btn-loading {
                    pointer-events: none;
                    opacity: .8;
                }
            `;
            document.head.appendChild( style );
        }
    }

    /* =====================================================================
       Init
       ===================================================================== */

    function init() {
        initObservers();
        initTiltEffect();
        initAnalyzeButtons();
        initProjectModal();
        initRipple();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
