/**
 * PédagoLens Landing — Front-end JS
 *
 * - IntersectionObserver pour animations au scroll
 * - Smooth scroll pour les ancres
 * - Counter animation pour les stats
 * - Score bars animation
 * - Nav scroll effect
 * - Parallax orbs
 */
( function () {
    'use strict';

    // =========================================================================
    // 1. INTERSECTION OBSERVER — Fade-in au scroll
    // =========================================================================

    function initScrollAnimations() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '.pl-animate-in' ).forEach( function ( el ) {
                el.classList.add( 'pl-visible' );
            } );
            return;
        }

        var observer = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    entry.target.classList.add( 'pl-visible' );
                    observer.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.1, rootMargin: '0px 0px -60px 0px' } );

        document.querySelectorAll( '.pl-animate-in' ).forEach( function ( el ) {
            observer.observe( el );
        } );
    }

    // =========================================================================
    // 2. COUNTER ANIMATION — Animate numbers from 0 to target
    // =========================================================================

    function animateCounters() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '[data-count-to]' ).forEach( function ( el ) {
                el.textContent = el.getAttribute( 'data-count-to' ) + ( el.getAttribute( 'data-count-suffix' ) || '' );
            } );
            return;
        }

        var counterObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var el = entry.target;
                    var target = parseInt( el.getAttribute( 'data-count-to' ), 10 );
                    var suffix = el.getAttribute( 'data-count-suffix' ) || '';
                    var duration = 2200;
                    var startTime = null;

                    function step( timestamp ) {
                        if ( ! startTime ) startTime = timestamp;
                        var progress = Math.min( ( timestamp - startTime ) / duration, 1 );
                        // Ease out expo for smooth deceleration
                        var eased = 1 - Math.pow( 2, -10 * progress );
                        var current = Math.floor( eased * target );
                        el.textContent = current + suffix;
                        if ( progress < 1 ) {
                            window.requestAnimationFrame( step );
                        } else {
                            el.textContent = target + suffix;
                        }
                    }

                    window.requestAnimationFrame( step );
                    counterObserver.unobserve( el );
                }
            } );
        }, { threshold: 0.3 } );

        document.querySelectorAll( '[data-count-to]' ).forEach( function ( el ) {
            el.textContent = '0';
            counterObserver.observe( el );
        } );
    }

    // =========================================================================
    // 3. SCORE BARS ANIMATION
    // =========================================================================

    function animateScoreBars() {
        if ( ! ( 'IntersectionObserver' in window ) ) {
            document.querySelectorAll( '.pl-score-bar' ).forEach( function ( bar ) {
                bar.classList.add( 'pl-animated' );
            } );
            return;
        }

        var barObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var bars = entry.target.querySelectorAll( '.pl-score-bar' );
                    bars.forEach( function ( bar, index ) {
                        setTimeout( function () {
                            bar.classList.add( 'pl-animated' );
                        }, index * 120 );
                    } );
                    barObserver.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.15 } );

        document.querySelectorAll( '.pl-score-bars' ).forEach( function ( container ) {
            barObserver.observe( container );
        } );
    }

    // =========================================================================
    // 4. SMOOTH SCROLL — Anchor links
    // =========================================================================

    function initSmoothScroll() {
        document.querySelectorAll( 'a[href^="#"]' ).forEach( function ( anchor ) {
            anchor.addEventListener( 'click', function ( e ) {
                var targetId = this.getAttribute( 'href' );
                if ( targetId === '#' ) return;
                var targetEl = document.querySelector( targetId );
                if ( targetEl ) {
                    e.preventDefault();
                    var navHeight = 72;
                    var targetPosition = targetEl.getBoundingClientRect().top + window.pageYOffset - navHeight;
                    window.scrollTo( {
                        top: targetPosition,
                        behavior: 'smooth'
                    } );
                }
            } );
        } );
    }

    // =========================================================================
    // 5. NAV SCROLL EFFECT — Add class on scroll
    // =========================================================================

    function initNavScroll() {
        var nav = document.querySelector( '.pl-landing-nav' );
        if ( ! nav ) return;

        var scrollThreshold = 50;
        var ticking = false;

        function updateNav() {
            if ( window.pageYOffset > scrollThreshold ) {
                nav.classList.add( 'pl-nav-scrolled' );
            } else {
                nav.classList.remove( 'pl-nav-scrolled' );
            }
            ticking = false;
        }

        window.addEventListener( 'scroll', function () {
            if ( ! ticking ) {
                window.requestAnimationFrame( updateNav );
                ticking = true;
            }
        }, { passive: true } );

        // Initial check
        updateNav();
    }

    // =========================================================================
    // 6. PARALLAX ORBS — Subtle mouse-follow effect
    // =========================================================================

    function initParallaxOrbs() {
        var orbs = document.querySelectorAll( '.pl-hero-orb' );
        if ( ! orbs.length ) return;

        // Only on desktop
        if ( window.innerWidth < 768 ) return;

        var ticking = false;

        document.addEventListener( 'mousemove', function ( e ) {
            if ( ticking ) return;
            ticking = true;

            window.requestAnimationFrame( function () {
                var x = ( e.clientX / window.innerWidth - 0.5 ) * 2;
                var y = ( e.clientY / window.innerHeight - 0.5 ) * 2;

                orbs.forEach( function ( orb, i ) {
                    var factor = ( i + 1 ) * 8;
                    orb.style.transform = 'translate(' + ( x * factor ) + 'px, ' + ( y * factor ) + 'px)';
                } );

                ticking = false;
            } );
        }, { passive: true } );
    }

    // =========================================================================
    // 7. STAGGER CHILDREN — Animate children with delay
    // =========================================================================

    function initStaggerAnimations() {
        if ( ! ( 'IntersectionObserver' in window ) ) return;

        var staggerObserver = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    var children = entry.target.querySelectorAll( '.pl-animate-in' );
                    children.forEach( function ( child, index ) {
                        child.style.transitionDelay = ( index * 0.1 ) + 's';
                        child.classList.add( 'pl-visible' );
                    } );
                    staggerObserver.unobserve( entry.target );
                }
            } );
        }, { threshold: 0.1 } );

        document.querySelectorAll( '.pl-features-grid, .pl-phase2-grid, .pl-problem-stats' ).forEach( function ( container ) {
            staggerObserver.observe( container );
        } );
    }

    // =========================================================================
    // 8. INIT
    // =========================================================================

    function init() {
        initScrollAnimations();
        animateCounters();
        animateScoreBars();
        initSmoothScroll();
        initNavScroll();
        initParallaxOrbs();
        initStaggerAnimations();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();


// =========================================================================
// 9. LOGIN / REGISTER PAGE
// =========================================================================

( function () {
    'use strict';

    function initLoginPage() {
        var loginCard = document.querySelector( '.pl-login-card' );
        if ( ! loginCard ) return;

        // -----------------------------------------------------------------
        // Tabs
        // -----------------------------------------------------------------
        var tabs   = loginCard.querySelectorAll( '.pl-login-tab' );
        var panels = loginCard.querySelectorAll( '.pl-login-panel' );

        tabs.forEach( function ( tab ) {
            tab.addEventListener( 'click', function () {
                var target = this.getAttribute( 'data-tab' );
                tabs.forEach( function ( t ) { t.classList.remove( 'pl-login-tab--active' ); } );
                panels.forEach( function ( p ) { p.classList.remove( 'pl-login-panel--active' ); } );
                this.classList.add( 'pl-login-tab--active' );
                var panel = document.getElementById( 'pl-panel-' + target );
                if ( panel ) panel.classList.add( 'pl-login-panel--active' );
            } );
        } );

        // -----------------------------------------------------------------
        // Role selection
        // -----------------------------------------------------------------
        var roleCards    = loginCard.querySelectorAll( '.pl-role-card' );
        var stepRole     = document.getElementById( 'pl-register-step-role' );
        var stepForm     = document.getElementById( 'pl-register-step-form' );
        var roleInput    = document.getElementById( 'pl-register-role' );
        var backBtn      = loginCard.querySelector( '.pl-register-back' );
        var teacherFields = loginCard.querySelectorAll( '.pl-field-teacher' );
        var studentFields = loginCard.querySelectorAll( '.pl-field-student' );

        roleCards.forEach( function ( card ) {
            card.addEventListener( 'click', function () {
                var role = this.getAttribute( 'data-role' );
                roleInput.value = role;
                stepRole.style.display = 'none';
                stepForm.style.display = 'block';

                teacherFields.forEach( function ( f ) { f.style.display = role === 'teacher' ? 'block' : 'none'; } );
                studentFields.forEach( function ( f ) { f.style.display = role === 'student' ? 'block' : 'none'; } );
            } );
        } );

        if ( backBtn ) {
            backBtn.addEventListener( 'click', function () {
                stepForm.style.display = 'none';
                stepRole.style.display = 'block';
                roleInput.value = '';
            } );
        }

        // -----------------------------------------------------------------
        // Difficulties checkbox → open modal
        // -----------------------------------------------------------------
        var diffCheck = document.getElementById( 'pl-reg-difficulties-check' );
        var diffModal = document.getElementById( 'pl-difficulties-modal' );

        if ( diffCheck && diffModal ) {
            diffCheck.addEventListener( 'change', function () {
                if ( this.checked ) {
                    diffModal.style.display = 'flex';
                }
            } );

            // Close modal
            var closeBtn  = diffModal.querySelector( '.pl-diff-modal-close' );
            var backdrop  = diffModal.querySelector( '.pl-diff-modal-backdrop' );

            function closeModal() {
                diffModal.style.display = 'none';
                // If no difficulties selected, uncheck the trigger
                var anyChecked = diffModal.querySelectorAll( 'input[name="diff[]"]:checked' );
                if ( anyChecked.length === 0 ) {
                    diffCheck.checked = false;
                }
            }

            if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
            if ( backdrop ) backdrop.addEventListener( 'click', closeModal );

            // "Autre" field toggle
            var autreCheckbox = diffModal.querySelector( 'input[value="autre"]' );
            var autreField    = diffModal.querySelector( '.pl-diff-autre-field' );
            if ( autreCheckbox && autreField ) {
                autreCheckbox.addEventListener( 'change', function () {
                    autreField.style.display = this.checked ? 'block' : 'none';
                } );
            }

            // "Plus précisément" button
            var moreBtn  = diffModal.querySelector( '.pl-diff-more-btn' );
            var moreZone = diffModal.querySelector( '.pl-diff-more' );
            if ( moreBtn && moreZone ) {
                moreBtn.addEventListener( 'click', function () {
                    moreZone.style.display = 'block';
                    this.classList.add( 'pl-hidden' );
                } );
            }

            // Save button in modal
            var saveBtn = diffModal.querySelector( '.pl-diff-modal-save' );
            if ( saveBtn ) {
                saveBtn.addEventListener( 'click', function () {
                    diffModal.style.display = 'none';
                } );
            }
        }

        // -----------------------------------------------------------------
        // Helper: show message
        // -----------------------------------------------------------------
        function showMsg( id, text, type ) {
            var el = document.getElementById( id );
            if ( ! el ) return;
            el.className = 'pl-login-msg pl-login-msg--' + type;
            el.textContent = text;
            el.style.display = 'block';
        }

        // -----------------------------------------------------------------
        // Login form AJAX
        // -----------------------------------------------------------------
        var loginForm = document.getElementById( 'pl-login-form' );
        if ( loginForm ) {
            loginForm.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                var btn   = loginForm.querySelector( '.pl-login-submit' );
                var email = loginForm.querySelector( '[name="email"]' ).value.trim();
                var pass  = loginForm.querySelector( '[name="password"]' ).value;
                var nonce = loginForm.querySelector( '[name="_wpnonce"]' ).value;

                if ( ! email || ! pass ) {
                    showMsg( 'pl-login-msg', 'Veuillez remplir tous les champs.', 'error' );
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Connexion…';

                var data = new FormData();
                data.append( 'action', 'pl_login' );
                data.append( '_wpnonce', nonce );
                data.append( 'email', email );
                data.append( 'password', pass );

                fetch( ( window.plFront && plFront.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success && res.data && res.data.redirect ) {
                        showMsg( 'pl-login-msg', 'Connexion r\u00e9ussie ! Redirection…', 'success' );
                        window.location.href = res.data.redirect;
                    } else {
                        showMsg( 'pl-login-msg', ( res.data && res.data.message ) || 'Erreur de connexion.', 'error' );
                        btn.disabled = false;
                        btn.textContent = 'Se connecter';
                    }
                } )
                .catch( function () {
                    showMsg( 'pl-login-msg', 'Erreur r\u00e9seau.', 'error' );
                    btn.disabled = false;
                    btn.textContent = 'Se connecter';
                } );
            } );
        }

        // -----------------------------------------------------------------
        // Register form AJAX
        // -----------------------------------------------------------------
        var registerForm = document.getElementById( 'pl-register-form' );
        if ( registerForm ) {
            registerForm.addEventListener( 'submit', function ( e ) {
                e.preventDefault();
                var btn   = registerForm.querySelector( '.pl-login-submit' );
                var nonce = registerForm.querySelector( '[name="_wpnonce"]' ).value;
                var role  = registerForm.querySelector( '[name="role"]' ).value;
                var name  = registerForm.querySelector( '[name="display_name"]' ).value.trim();
                var email = registerForm.querySelector( '[name="email"]' ).value.trim();
                var pass  = registerForm.querySelector( '[name="password"]' ).value;
                var pass2 = registerForm.querySelector( '[name="password_confirm"]' ).value;
                var inst  = registerForm.querySelector( '[name="institute"]' );
                var institute = inst ? inst.value.trim() : '';

                // Client-side validation
                if ( ! name || ! email || ! pass || ! pass2 ) {
                    showMsg( 'pl-register-msg', 'Veuillez remplir tous les champs obligatoires.', 'error' );
                    return;
                }
                if ( pass.length < 6 ) {
                    showMsg( 'pl-register-msg', 'Le mot de passe doit contenir au moins 6 caract\u00e8res.', 'error' );
                    return;
                }
                if ( pass !== pass2 ) {
                    showMsg( 'pl-register-msg', 'Les mots de passe ne correspondent pas.', 'error' );
                    return;
                }

                // Collect difficulties from modal
                var difficulties = [];
                if ( diffModal && role === 'student' ) {
                    var checked = diffModal.querySelectorAll( 'input[name="diff[]"]:checked' );
                    var contextText = '';
                    var contextEl = document.getElementById( 'pl-diff-context' );
                    if ( contextEl ) contextText = contextEl.value.trim();

                    checked.forEach( function ( cb ) {
                        var val = cb.value;
                        if ( val === 'autre' ) {
                            var autreText = document.getElementById( 'pl-diff-autre-text' );
                            difficulties.push( { key: 'autre', text: autreText ? autreText.value.trim() : '', context: contextText } );
                        } else {
                            difficulties.push( val );
                        }
                    } );

                    // If context but no "autre", attach to first or standalone
                    if ( contextText && difficulties.length > 0 && ! difficulties.some( function(d) { return typeof d === 'object'; } ) ) {
                        difficulties.push( { key: 'context', text: contextText, context: contextText } );
                    }
                }

                btn.disabled = true;
                btn.textContent = 'Cr\u00e9ation…';

                var data = new FormData();
                data.append( 'action', 'pl_register' );
                data.append( '_wpnonce', nonce );
                data.append( 'role', role );
                data.append( 'display_name', name );
                data.append( 'email', email );
                data.append( 'password', pass );
                data.append( 'password_confirm', pass2 );
                data.append( 'institute', institute );
                data.append( 'difficulties', JSON.stringify( difficulties ) );

                fetch( ( window.plFront && plFront.ajaxUrl ) || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success && res.data && res.data.redirect ) {
                        showMsg( 'pl-register-msg', 'Compte cr\u00e9\u00e9 ! Redirection…', 'success' );
                        window.location.href = res.data.redirect;
                    } else {
                        showMsg( 'pl-register-msg', ( res.data && res.data.message ) || 'Erreur lors de l\'inscription.', 'error' );
                        btn.disabled = false;
                        btn.textContent = 'Cr\u00e9er mon compte';
                    }
                } )
                .catch( function () {
                    showMsg( 'pl-register-msg', 'Erreur r\u00e9seau.', 'error' );
                    btn.disabled = false;
                    btn.textContent = 'Cr\u00e9er mon compte';
                } );
            } );
        }
    }

    // Init on DOM ready
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLoginPage );
    } else {
        initLoginPage();
    }

} )();
