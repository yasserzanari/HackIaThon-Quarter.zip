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
