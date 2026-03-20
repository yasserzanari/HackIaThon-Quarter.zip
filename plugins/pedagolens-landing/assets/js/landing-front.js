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
