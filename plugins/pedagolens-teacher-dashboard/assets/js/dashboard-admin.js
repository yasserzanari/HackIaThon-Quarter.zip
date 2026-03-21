/**
 * PédagoLens Teacher Dashboard — Professional JS v3
 * Sidebar navigation, course detail view, modals, AJAX, animations
 */
( function () {
    'use strict';

    /* =====================================================================
       Helpers
       ===================================================================== */

    function esc( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    function getAjaxConfig() {
        if ( typeof plFront !== 'undefined' ) {
            return { url: plFront.ajaxUrl, nonce: ( plFront.nonces && plFront.nonces.dashboard ) || '', i18n: plFront.i18n || {} };
        }
        if ( typeof plDashboard !== 'undefined' ) {
            return { url: plDashboard.ajaxUrl, nonce: plDashboard.nonce, i18n: plDashboard.i18n || {} };
        }
        return null;
    }

    function getCoursesData() {
        try {
            var el = document.getElementById( 'pl-courses-json' );
            return el ? JSON.parse( el.textContent ) : [];
        } catch ( e ) { return []; }
    }

    /* =====================================================================
       Sidebar Navigation
       ===================================================================== */

    function initSidebar() {
        var hamburger = document.getElementById( 'pl-hamburger' );
        var sidebar   = document.getElementById( 'pl-sidebar' );
        if ( ! hamburger || ! sidebar ) return;

        // Create overlay
        var overlay = document.querySelector( '.pl-sidebar-overlay' );
        if ( ! overlay ) {
            overlay = document.createElement( 'div' );
            overlay.className = 'pl-sidebar-overlay';
            sidebar.parentNode.insertBefore( overlay, sidebar.nextSibling );
        }

        function toggleSidebar() {
            var open = sidebar.classList.toggle( 'pl-sidebar-open' );
            hamburger.classList.toggle( 'active', open );
            overlay.classList.toggle( 'active', open );
        }

        hamburger.addEventListener( 'click', toggleSidebar );
        overlay.addEventListener( 'click', toggleSidebar );

        // Sidebar view links
        document.querySelectorAll( '.pl-sidebar-link[data-view]' ).forEach( function ( link ) {
            link.addEventListener( 'click', function ( e ) {
                e.preventDefault();
                var view = this.dataset.view;
                switchView( view );

                document.querySelectorAll( '.pl-sidebar-link' ).forEach( function ( l ) { l.classList.remove( 'pl-sidebar-active' ); } );
                this.classList.add( 'pl-sidebar-active' );

                if ( window.innerWidth <= 768 ) {
                    sidebar.classList.remove( 'pl-sidebar-open' );
                    hamburger.classList.remove( 'active' );
                    overlay.classList.remove( 'active' );
                }
            } );
        } );
    }

    function switchView( viewName ) {
        document.querySelectorAll( '.pl-dash-view' ).forEach( function ( v ) { v.classList.remove( 'pl-dash-view--active' ); } );
        var target = document.getElementById( 'pl-view-' + viewName );
        if ( target ) {
            target.classList.add( 'pl-dash-view--active' );
            target.querySelectorAll( '.pl-animate-in' ).forEach( function ( el ) {
                el.classList.remove( 'pl-visible' );
                void el.offsetWidth;
            } );
            initObservers();
        }
    }

    /* =====================================================================
       Course Detail View (Open button)
       ===================================================================== */

    function initCourseDetail() {
        document.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '.pl-btn-open-course' );
            if ( ! btn ) return;

            var courseId = parseInt( btn.dataset.courseId, 10 );
            var courses  = getCoursesData();
            var course   = null;
            for ( var i = 0; i < courses.length; i++ ) {
                if ( courses[ i ].id === courseId ) { course = courses[ i ]; break; }
            }
            if ( ! course ) return;

            renderCourseDetail( course );
            switchView( 'courses' );

            document.querySelectorAll( '.pl-sidebar-link' ).forEach( function ( l ) { l.classList.remove( 'pl-sidebar-active' ); } );
            var coursesLink = document.querySelector( '.pl-sidebar-link[data-view="courses"]' );
            if ( coursesLink ) coursesLink.classList.add( 'pl-sidebar-active' );
        } );

        // Back button
        document.addEventListener( 'click', function ( e ) {
            if ( ! e.target.closest( '.pl-btn-back-overview' ) ) return;
            switchView( 'overview' );
            document.querySelectorAll( '.pl-sidebar-link' ).forEach( function ( l ) { l.classList.remove( 'pl-sidebar-active' ); } );
            var overviewLink = document.querySelector( '.pl-sidebar-link[data-view="overview"]' );
            if ( overviewLink ) overviewLink.classList.add( 'pl-sidebar-active' );
        } );
    }


    function renderCourseDetail( course ) {
        var container = document.getElementById( 'pl-course-detail-content' );
        if ( ! container ) return;

        var typeBadge = '<span class="pl-badge pl-type-' + esc( course.type ) + '">' + esc( course.type ) + '</span>';

        var projectsHtml = '';
        if ( course.projects.length ) {
            projectsHtml = '<h3 class="pl-detail-projects-title">📄 Projets (' + course.projects.length + ')</h3><div class="pl-detail-project-list">';
            for ( var i = 0; i < course.projects.length; i++ ) {
                var p = course.projects[ i ];
                projectsHtml += '<div class="pl-detail-project-row">' +
                    '<div class="pl-detail-project-info">' +
                        '<span class="pl-detail-project-title">' + esc( p.title ) + '</span>' +
                        '<span class="pl-badge pl-type-' + esc( p.type ) + '">' + esc( p.type ) + '</span>' +
                        ( p.date ? '<span class="pl-detail-project-date">' + esc( p.date ) + '</span>' : '' ) +
                    '</div>' +
                    '<a href="' + esc( p.url ) + '" class="pl-btn-glow pl-btn-sm">📝 Ouvrir dans le Workbench</a>' +
                '</div>';
            }
            projectsHtml += '</div>';
        } else {
            projectsHtml = '<div class="pl-detail-empty">Aucun projet pour ce cours. Créez-en un !</div>';
        }

        container.innerHTML =
            '<div class="pl-detail-header">' +
                '<div>' +
                    '<h2>' + esc( course.title ) + '</h2>' +
                    '<div style="margin-top:8px">' + typeBadge + ' <span style="color:var(--pl-text-muted);font-size:13px;margin-left:12px">📅 ' + esc( course.date ) + '</span></div>' +
                '</div>' +
                '<div class="pl-detail-actions">' +
                    '<button class="pl-btn-glow pl-btn-sm pl-btn-analyze-front" data-course-id="' + course.id + '">🔍 Analyser</button>' +
                    '<button class="pl-btn-ghost pl-btn-sm pl-btn-create-project" data-course-id="' + course.id + '" data-course-title="' + esc( course.title ) + '">➕ Nouveau projet</button>' +
                '</div>' +
            '</div>' +
            '<div id="pl-analysis-result-' + course.id + '" class="pl-analysis-front-result"></div>' +
            projectsHtml;

        // Load existing analysis if present on the overview card
        var existingResult = document.querySelector( '#pl-view-overview #pl-analysis-result-' + course.id );
        if ( existingResult && existingResult.innerHTML.trim() ) {
            var detailResult = container.querySelector( '#pl-analysis-result-' + course.id );
            if ( detailResult ) {
                detailResult.innerHTML = existingResult.innerHTML;
                animateScoreBars( detailResult );
            }
        }
    }

    /* =====================================================================
       Counter Animation
       ===================================================================== */

    function animateCounter( el ) {
        var target = parseInt( el.dataset.target, 10 ) || 0;
        if ( target === 0 ) { el.textContent = '0'; return; }
        var duration = 1600;
        var start    = performance.now();
        function step( now ) {
            var elapsed  = now - start;
            var progress = Math.min( elapsed / duration, 1 );
            var ease = progress === 1 ? 1 : 1 - Math.pow( 2, -10 * progress );
            el.textContent = Math.round( ease * target );
            if ( progress < 1 ) requestAnimationFrame( step );
        }
        requestAnimationFrame( step );
    }

    /* =====================================================================
       Tilt Effect on stat cards
       ===================================================================== */

    function initTiltEffect() {
        document.querySelectorAll( '.pl-stat-card' ).forEach( function ( card ) {
            card.addEventListener( 'mousemove', function ( e ) {
                var rect = card.getBoundingClientRect();
                var x = ( e.clientX - rect.left ) / rect.width - 0.5;
                var y = ( e.clientY - rect.top ) / rect.height - 0.5;
                card.style.transform = 'translateY(-4px) perspective(600px) rotateX(' + ( -y * 6 ) + 'deg) rotateY(' + ( x * 6 ) + 'deg)';
            } );
            card.addEventListener( 'mouseleave', function () { card.style.transform = ''; } );
        } );
    }


    /* =====================================================================
       IntersectionObserver
       ===================================================================== */

    function initObservers() {
        var animateEls = document.querySelectorAll( '.pl-animate-in:not(.pl-visible)' );
        if ( animateEls.length ) {
            var obs = new IntersectionObserver( function ( entries ) {
                entries.forEach( function ( entry ) {
                    if ( entry.isIntersecting ) {
                        entry.target.classList.add( 'pl-visible' );
                        obs.unobserve( entry.target );
                    }
                } );
            }, { threshold: 0.08 } );
            animateEls.forEach( function ( el ) { obs.observe( el ); } );
        }

        var counters = document.querySelectorAll( '.pl-stat-number[data-target]' );
        if ( counters.length ) {
            var cObs = new IntersectionObserver( function ( entries ) {
                entries.forEach( function ( entry ) {
                    if ( entry.isIntersecting ) {
                        var card = entry.target.closest( '.pl-stat-card' );
                        var idx = card ? Array.from( card.parentNode.children ).indexOf( card ) : 0;
                        setTimeout( function () { animateCounter( entry.target ); }, idx * 120 );
                        cObs.unobserve( entry.target );
                    }
                } );
            }, { threshold: 0.3 } );
            counters.forEach( function ( el ) { cObs.observe( el ); } );
        }

        animateScoreBars( document );
    }

    function animateScoreBars( container ) {
        var bars = container.querySelectorAll( '.pl-score-bar[data-score]' );
        if ( ! bars.length ) return;
        var bObs = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var bar   = entry.target;
                    var score = parseInt( bar.dataset.score, 10 ) || 0;
                    var row   = bar.closest( '.pl-score-row' );
                    var idx   = row ? Array.from( row.parentNode.children ).indexOf( row ) : 0;
                    setTimeout( function () { bar.style.width = score + '%'; }, 150 + idx * 100 );
                    bObs.unobserve( bar );
                }
            } );
        }, { threshold: 0.1 } );
        bars.forEach( function ( el ) { bObs.observe( el ); } );
    }


    /* =====================================================================
       Modal Utility
       ===================================================================== */

    function createModal( title, fields, onSubmit ) {
        var existing = document.querySelector( '.pl-modal-overlay' );
        if ( existing ) existing.remove();

        var overlay = document.createElement( 'div' );
        overlay.className = 'pl-modal-overlay';

        var fieldsHtml = '';
        for ( var i = 0; i < fields.length; i++ ) {
            var f = fields[ i ];
            fieldsHtml += '<label for="pl-modal-field-' + esc( f.name ) + '">' + esc( f.label ) + '</label>';
            if ( f.type === 'select' && f.options ) {
                fieldsHtml += '<select id="pl-modal-field-' + esc( f.name ) + '" name="' + esc( f.name ) + '">';
                for ( var j = 0; j < f.options.length; j++ ) {
                    var opt = f.options[ j ];
                    fieldsHtml += '<option value="' + esc( opt.value ) + '">' + esc( opt.label ) + '</option>';
                }
                fieldsHtml += '</select>';
            } else {
                fieldsHtml += '<input type="text" id="pl-modal-field-' + esc( f.name ) + '" name="' + esc( f.name ) + '" placeholder="' + esc( f.placeholder || '' ) + '" />';
            }
        }

        overlay.innerHTML =
            '<div class="pl-modal-box">' +
                '<button type="button" class="pl-modal-close">&times;</button>' +
                '<h2>' + esc( title ) + '</h2>' +
                '<form class="pl-modal-form">' +
                    fieldsHtml +
                    '<div class="pl-modal-error"></div>' +
                    '<div class="pl-modal-actions">' +
                        '<button type="button" class="pl-btn-ghost pl-btn-sm pl-modal-cancel">Annuler</button>' +
                        '<button type="submit" class="pl-btn-glow pl-btn-sm">Confirmer</button>' +
                    '</div>' +
                '</form>' +
            '</div>';

        document.body.appendChild( overlay );

        function closeModal() { overlay.remove(); }
        overlay.querySelector( '.pl-modal-close' ).addEventListener( 'click', closeModal );
        overlay.querySelector( '.pl-modal-cancel' ).addEventListener( 'click', closeModal );
        overlay.addEventListener( 'click', function ( e ) {
            if ( e.target === overlay ) closeModal();
        } );

        overlay.querySelector( '.pl-modal-form' ).addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            var data = {};
            for ( var k = 0; k < fields.length; k++ ) {
                var input = overlay.querySelector( '[name="' + fields[ k ].name + '"]' );
                data[ fields[ k ].name ] = input ? input.value.trim() : '';
            }
            var errorEl = overlay.querySelector( '.pl-modal-error' );
            onSubmit( data, errorEl, closeModal );
        } );

        var firstInput = overlay.querySelector( 'input, select' );
        if ( firstInput ) setTimeout( function () { firstInput.focus(); }, 100 );

        return overlay;
    }


    /* =====================================================================
       AJAX — Analyze Course
       ===================================================================== */

    function initAnalyze() {
        document.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '.pl-btn-analyze-front' ) || e.target.closest( '.pl-btn-analyze' );
            if ( ! btn ) return;

            var cfg = getAjaxConfig();
            if ( ! cfg ) return;

            var courseId  = btn.dataset.courseId;
            var container = document.getElementById( 'pl-analysis-result-' + courseId )
                         || document.getElementById( 'pl-analysis-' + courseId );
            if ( ! container ) return;

            container.innerHTML = '<div class="pl-loading-pulse">Analyse en cours\u2026</div>';
            btn.disabled = true;
            btn.style.opacity = '0.6';

            var xhr = new XMLHttpRequest();
            xhr.open( 'POST', cfg.url, true );
            xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
            xhr.onreadystatechange = function () {
                if ( xhr.readyState !== 4 ) return;
                btn.disabled = false;
                btn.style.opacity = '';

                try {
                    var resp = JSON.parse( xhr.responseText );
                    if ( resp.success && resp.data && resp.data.html ) {
                        container.innerHTML = resp.data.html;
                        animateScoreBars( container );
                    } else {
                        var msg = ( resp.data && resp.data.message ) ? resp.data.message : ( cfg.i18n.analyzeError || 'Erreur lors de l\'analyse.' );
                        container.innerHTML = '<div class="pl-notice pl-notice-error"><p>' + esc( msg ) + '</p></div>';
                    }
                } catch ( err ) {
                    container.innerHTML = '<div class="pl-notice pl-notice-error"><p>' + esc( cfg.i18n.analyzeError || 'Erreur lors de l\'analyse.' ) + '</p></div>';
                }
            };
            xhr.send( 'action=pl_analyze_course&course_id=' + encodeURIComponent( courseId ) + '&nonce=' + encodeURIComponent( cfg.nonce ) );
        } );
    }


    /* =====================================================================
       AJAX — Create Project (modal)
       ===================================================================== */

    function initCreateProject() {
        document.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '.pl-btn-create-project' ) || e.target.closest( '.pl-btn-new-project' );
            if ( ! btn ) return;

            var cfg = getAjaxConfig();
            if ( ! cfg ) return;

            var courseId    = btn.dataset.courseId;
            var courseTitle = btn.dataset.courseTitle || '';

            createModal(
                'Nouveau projet' + ( courseTitle ? ' \u2014 ' + courseTitle : '' ),
                [
                    { name: 'title', label: 'Titre du projet', type: 'text', placeholder: 'Ex: TP Algorithmes' },
                    {
                        name: 'type',
                        label: 'Type de projet',
                        type: 'select',
                        options: [
                            { value: 'exercice',       label: 'Exercice' },
                            { value: 'evaluation',     label: '\u00c9valuation' },
                            { value: 'travail_equipe', label: 'Travail d\'\u00e9quipe' }
                        ]
                    }
                ],
                function ( data, errorEl, closeModal ) {
                    if ( ! data.title ) {
                        errorEl.textContent = 'Le titre est requis.';
                        errorEl.style.display = 'block';
                        return;
                    }
                    errorEl.style.display = 'none';
                    var submitBtn = document.querySelector( '.pl-modal-form .pl-btn-glow' );
                    if ( submitBtn ) { submitBtn.disabled = true; submitBtn.textContent = 'Cr\u00e9ation\u2026'; }

                    var xhr = new XMLHttpRequest();
                    xhr.open( 'POST', cfg.url, true );
                    xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
                    xhr.onreadystatechange = function () {
                        if ( xhr.readyState !== 4 ) return;
                        try {
                            var resp = JSON.parse( xhr.responseText );
                            if ( resp.success ) {
                                closeModal();
                                window.location.reload();
                            } else {
                                var msg = ( resp.data && resp.data.message ) ? resp.data.message : 'Erreur lors de la cr\u00e9ation.';
                                errorEl.textContent = msg;
                                errorEl.style.display = 'block';
                                if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer'; }
                            }
                        } catch ( err ) {
                            errorEl.textContent = 'Erreur r\u00e9seau.';
                            errorEl.style.display = 'block';
                            if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer'; }
                        }
                    };
                    xhr.send(
                        'action=pl_create_project' +
                        '&course_id=' + encodeURIComponent( courseId ) +
                        '&title='     + encodeURIComponent( data.title ) +
                        '&type='      + encodeURIComponent( data.type ) +
                        '&nonce='     + encodeURIComponent( cfg.nonce )
                    );
                }
            );
        } );
    }


    /* =====================================================================
       AJAX — Add Course (modal)
       ===================================================================== */

    function initAddCourse() {
        document.addEventListener( 'click', function ( e ) {
            var btn = e.target.closest( '#pl-btn-add-course' );
            if ( ! btn ) return;

            var cfg = getAjaxConfig();
            if ( ! cfg ) return;

            createModal(
                'Ajouter un cours',
                [
                    { name: 'title', label: 'Titre du cours', type: 'text', placeholder: 'Ex: Introduction \u00e0 la programmation' },
                    {
                        name: 'course_type',
                        label: 'Type de cours',
                        type: 'select',
                        options: [
                            { value: 'magistral',      label: 'Magistral' },
                            { value: 'exercice',       label: 'Exercice' },
                            { value: 'evaluation',     label: '\u00c9valuation' },
                            { value: 'travail_equipe', label: 'Travail d\'\u00e9quipe' }
                        ]
                    }
                ],
                function ( data, errorEl, closeModal ) {
                    if ( ! data.title ) {
                        errorEl.textContent = 'Le titre est requis.';
                        errorEl.style.display = 'block';
                        return;
                    }
                    errorEl.style.display = 'none';
                    var submitBtn = document.querySelector( '.pl-modal-form .pl-btn-glow' );
                    if ( submitBtn ) { submitBtn.disabled = true; submitBtn.textContent = 'Cr\u00e9ation\u2026'; }

                    var xhr = new XMLHttpRequest();
                    xhr.open( 'POST', cfg.url, true );
                    xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
                    xhr.onreadystatechange = function () {
                        if ( xhr.readyState !== 4 ) return;
                        try {
                            var resp = JSON.parse( xhr.responseText );
                            if ( resp.success ) {
                                closeModal();
                                window.location.reload();
                            } else {
                                var msg = ( resp.data && resp.data.message ) ? resp.data.message : 'Erreur lors de la cr\u00e9ation.';
                                errorEl.textContent = msg;
                                errorEl.style.display = 'block';
                                if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer'; }
                            }
                        } catch ( err ) {
                            errorEl.textContent = 'Erreur r\u00e9seau.';
                            errorEl.style.display = 'block';
                            if ( submitBtn ) { submitBtn.disabled = false; submitBtn.textContent = 'Confirmer'; }
                        }
                    };
                    xhr.send(
                        'action=pl_create_course' +
                        '&title='       + encodeURIComponent( data.title ) +
                        '&course_type=' + encodeURIComponent( data.course_type ) +
                        '&nonce='       + encodeURIComponent( cfg.nonce )
                    );
                }
            );
        } );
    }

    /* =====================================================================
       Init
       ===================================================================== */

    document.addEventListener( 'DOMContentLoaded', function () {
        initSidebar();
        initCourseDetail();
        initObservers();
        initTiltEffect();
        initAnalyze();
        initCreateProject();
        initAddCourse();
    } );

} )();
