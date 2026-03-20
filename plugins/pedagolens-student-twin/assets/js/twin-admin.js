/**
 * PédagoLens Student Twin — JS
 * Front-end student dashboard (shortcode) + admin demo page.
 */
( function( $ ) {
    'use strict';

    var ajaxUrl = plTwin.ajaxUrl;
    var plNonce = plTwin.nonce;
    var currentSessionId = null;

    function ajax( action, data ) {
        data = data || {};
        data.action = action;
        data.nonce  = plNonce;
        return $.post( ajaxUrl, data );
    }

    function escHtml( str ) {
        if ( ! str ) return '';
        var div = document.createElement( 'div' );
        div.appendChild( document.createTextNode( str ) );
        return div.innerHTML;
    }

    // =========================================================================
    // FRONT-END STUDENT DASHBOARD
    // =========================================================================

    var $dashboard = $( '.pl-twin-dashboard' ).not( '.pl-twin-logged-out' );

    if ( $dashboard.length ) {
        var $messages     = $( '#pl-twin-messages' );
        var $input        = $( '#pl-twin-input' );
        var $sendBtn      = $( '#pl-twin-send' );
        var $followUps    = $( '#pl-twin-follow-ups' );
        var $newSession   = $( '#pl-twin-new-session' );
        var $endSession   = $( '#pl-twin-end-session' );
        var $courseSelect  = $( '#pl-twin-course-select' );
        var twinName      = plTwin.twinName || 'Léa';
        var introMsg      = plTwin.introMessage || 'Bonjour !';
        var i18n          = plTwin.i18n || {};

        $newSession.on( 'click', function() {
            var courseId = parseInt( $courseSelect.val(), 10 );
            if ( ! courseId ) { shakeElement( $courseSelect ); return; }
            if ( currentSessionId ) {
                endCurrentSession( function() { startNewSession( courseId ); } );
            } else { startNewSession( courseId ); }
        } );

        function startNewSession( courseId ) {
            $newSession.prop( 'disabled', true );
            ajax( 'pl_twin_start_session', { course_id: courseId } )
                .done( function( res ) {
                    if ( res.success ) {
                        currentSessionId = res.data.session_id;
                        $messages.empty(); $followUps.empty();
                        addBubble( 'welcome',
                            '<span class="pl-twin-welcome-name">' + escHtml( twinName ) + '</span> — ' + escHtml( introMsg ), true );
                        enableChat( true ); $endSession.prop( 'disabled', false ); $input.trigger( 'focus' );
                    } else { addBubble( 'system', ( res.data && res.data.message ) || 'Erreur.' ); }
                } )
                .fail( function() { addBubble( 'system', i18n.networkError || 'Erreur réseau.' ); } )
                .always( function() { $newSession.prop( 'disabled', false ); } );
        }

        $endSession.on( 'click', function() { endCurrentSession(); } );

        function endCurrentSession( callback ) {
            if ( ! currentSessionId ) { if ( callback ) callback(); return; }
            $endSession.prop( 'disabled', true );
            ajax( 'pl_twin_end_session', { session_id: currentSessionId } )
                .done( function() {
                    addBubble( 'system', '✓ ' + ( i18n.sessionEnded || 'Session terminée.' ) );
                    currentSessionId = null; enableChat( false ); $followUps.empty();
                } )
                .always( function() { if ( callback ) callback(); } );
        }

        $sendBtn.on( 'click', sendDashboardMessage );
        $input.on( 'keydown', function( e ) {
            if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendDashboardMessage(); }
        } );
        $input.on( 'input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min( this.scrollHeight, 120 ) + 'px';
        } );

        function sendDashboardMessage() {
            if ( ! currentSessionId ) return;
            var message = $input.val().trim();
            if ( ! message ) return;
            $input.val( '' ).css( 'height', 'auto' );
            addBubble( 'user', escHtml( message ) );
            $followUps.empty(); enableChat( false );
            var $typing = showTypingIndicator();
            ajax( 'pl_twin_send_message', { session_id: currentSessionId, message: message } )
                .done( function( res ) {
                    $typing.remove();
                    if ( res.success ) {
                        addBubble( 'ai', escHtml( res.data.reply ) );
                        if ( res.data.guardrail_triggered ) {
                            addBubble( 'guardrail',
                                ( i18n.guardrailLabel || 'Garde-fou déclenché' ) +
                                ( res.data.guardrail_reason ? ' : ' + escHtml( res.data.guardrail_reason ) : '' ) );
                        }
                        renderFollowUps( res.data.follow_up_questions || [] );
                    } else { addBubble( 'ai', '✗ ' + ( ( res.data && res.data.message ) || 'Erreur.' ) ); }
                } )
                .fail( function() { $typing.remove(); addBubble( 'ai', '✗ ' + ( i18n.networkError || 'Erreur réseau.' ) ); } )
                .always( function() { enableChat( true ); $input.trigger( 'focus' ); } );
        }

        $followUps.on( 'click', '.pl-twin-follow-btn', function() {
            $input.val( $( this ).text() ); sendDashboardMessage();
        } );

        $( '#pl-twin-history-list' ).on( 'click', '.pl-twin-history-item', function() {
            var sessionId = $( this ).data( 'session-id' );
            if ( ! sessionId ) return;
            $( '.pl-twin-history-item' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $messages.empty(); $followUps.empty(); enableChat( false );
            var $typing = showTypingIndicator();
            ajax( 'pl_twin_get_history', { session_id: sessionId } )
                .done( function( res ) {
                    $typing.remove();
                    if ( res.success && res.data.messages ) {
                        res.data.messages.forEach( function( m ) {
                            if ( m.role === 'user' ) addBubble( 'user', escHtml( m.content ) );
                            else if ( m.role === 'assistant' ) addBubble( 'ai', escHtml( m.content ) );
                            if ( m.guardrail_triggered ) addBubble( 'guardrail', i18n.guardrailLabel || 'Garde-fou déclenché' );
                        } );
                    } else { addBubble( 'system', ( res.data && res.data.message ) || 'Historique introuvable.' ); }
                } )
                .fail( function() { $typing.remove(); addBubble( 'system', i18n.networkError || 'Erreur réseau.' ); } );
        } );

        // =====================================================================
        // HELPER FUNCTIONS — Front-end dashboard
        // =====================================================================

        function addBubble( type, content, isHtml ) {
            var cls = 'pl-twin-bubble pl-twin-bubble--' + type;
            var $bubble = $( '<div>', { 'class': cls } );
            if ( isHtml ) {
                $bubble.html( content );
            } else {
                $bubble.text( content );
            }
            $messages.append( $bubble );
            scrollToBottom();
            return $bubble;
        }

        function showTypingIndicator() {
            var label = twinName + ' ' + ( i18n.typing || 'est en train d\'écrire…' );
            var $typing = $(
                '<div class="pl-twin-typing">' +
                    '<span class="pl-twin-typing-label">' + escHtml( label ) + '</span>' +
                    '<span class="pl-twin-typing-dots">' +
                        '<span></span><span></span><span></span>' +
                    '</span>' +
                '</div>'
            );
            $messages.append( $typing );
            scrollToBottom();
            return $typing;
        }

        function renderFollowUps( questions ) {
            $followUps.empty();
            if ( ! questions || ! questions.length ) return;
            questions.forEach( function( q ) {
                var $btn = $( '<button>', {
                    'class': 'pl-twin-follow-btn',
                    'type':  'button',
                    'text':  q
                } );
                $followUps.append( $btn );
            } );
        }

        function enableChat( enabled ) {
            $input.prop( 'disabled', ! enabled );
            $sendBtn.prop( 'disabled', ! enabled );
            if ( enabled ) {
                $input.trigger( 'focus' );
            }
        }

        function shakeElement( $el ) {
            $el.addClass( 'pl-twin-shake' );
            setTimeout( function() { $el.removeClass( 'pl-twin-shake' ); }, 500 );
        }

        function scrollToBottom() {
            var el = $messages[0];
            if ( el ) {
                setTimeout( function() {
                    el.scrollTop = el.scrollHeight;
                }, 50 );
            }
        }

    } // end if $dashboard.length

    // =========================================================================
    // ADMIN DEMO TAB
    // =========================================================================

    var $startBtn  = $( '#pl-twin-start' );
    var $endBtn    = $( '#pl-twin-end' );
    var $chatBox   = $( '#pl-twin-chat' );
    var $chatMsgs  = $( '#pl-chat-messages' );
    var $chatInput = $( '#pl-chat-input' );
    var $chatSend  = $( '#pl-chat-send' );
    var $chatFU    = $( '#pl-chat-follow-ups' );
    var adminSessionId = null;

    if ( $startBtn.length ) {

        $startBtn.on( 'click', function() {
            var courseId = parseInt( $( '#pl-demo-course' ).val(), 10 );
            if ( ! courseId ) { alert( 'Sélectionnez un cours.' ); return; }
            $startBtn.prop( 'disabled', true );
            ajax( 'pl_twin_start_session', { course_id: courseId } )
                .done( function( res ) {
                    if ( res.success ) {
                        adminSessionId = res.data.session_id;
                        $chatBox.slideDown( 200 );
                        $chatMsgs.find( '.pl-chat-bubble' ).not( ':first' ).remove();
                        $chatFU.empty();
                        $chatInput.prop( 'disabled', false ).trigger( 'focus' );
                        $chatSend.prop( 'disabled', false );
                    } else {
                        alert( ( res.data && res.data.message ) || 'Erreur.' );
                    }
                } )
                .fail( function() { alert( 'Erreur réseau.' ); } )
                .always( function() { $startBtn.prop( 'disabled', false ); } );
        } );

        $endBtn.on( 'click', function() {
            if ( ! adminSessionId ) return;
            ajax( 'pl_twin_end_session', { session_id: adminSessionId } )
                .done( function() {
                    adminAddBubble( 'system', '✓ Session terminée.' );
                    adminSessionId = null;
                    $chatInput.prop( 'disabled', true );
                    $chatSend.prop( 'disabled', true );
                    $chatFU.empty();
                } );
        } );

        $chatSend.on( 'click', adminSendMessage );
        $chatInput.on( 'keydown', function( e ) {
            if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); adminSendMessage(); }
        } );

        $chatFU.on( 'click', '.pl-follow-up-btn', function() {
            $chatInput.val( $( this ).text() );
            adminSendMessage();
        } );

        function adminSendMessage() {
            if ( ! adminSessionId ) return;
            var msg = $chatInput.val().trim();
            if ( ! msg ) return;
            $chatInput.val( '' );
            adminAddBubble( 'user', escHtml( msg ) );
            $chatFU.empty();
            $chatInput.prop( 'disabled', true );
            $chatSend.prop( 'disabled', true );
            var $typing = adminShowTyping();
            ajax( 'pl_twin_send_message', { session_id: adminSessionId, message: msg } )
                .done( function( res ) {
                    $typing.remove();
                    if ( res.success ) {
                        adminAddBubble( 'assistant', escHtml( res.data.reply ) );
                        if ( res.data.guardrail_triggered ) {
                            adminAddBubble( 'system',
                                'Garde-fou : ' + escHtml( res.data.guardrail_reason || '' ) );
                        }
                        adminRenderFollowUps( res.data.follow_up_questions || [] );
                    } else {
                        adminAddBubble( 'system', ( res.data && res.data.message ) || 'Erreur.' );
                    }
                } )
                .fail( function() { $typing.remove(); adminAddBubble( 'system', 'Erreur réseau.' ); } )
                .always( function() {
                    $chatInput.prop( 'disabled', false ).trigger( 'focus' );
                    $chatSend.prop( 'disabled', false );
                } );
        }

        function adminAddBubble( role, content ) {
            var cls = 'pl-chat-bubble pl-bubble-' + role;
            var $b = $( '<div>', { 'class': cls } ).html( content );
            $chatMsgs.append( $b );
            $chatMsgs[0].scrollTop = $chatMsgs[0].scrollHeight;
            return $b;
        }

        function adminShowTyping() {
            var $t = $(
                '<div class="pl-chat-bubble pl-bubble-typing">' +
                    '<span class="pl-typing-dots"><span></span><span></span><span></span></span>' +
                '</div>'
            );
            $chatMsgs.append( $t );
            $chatMsgs[0].scrollTop = $chatMsgs[0].scrollHeight;
            return $t;
        }

        function adminRenderFollowUps( questions ) {
            $chatFU.empty();
            if ( ! questions || ! questions.length ) return;
            questions.forEach( function( q ) {
                $chatFU.append(
                    $( '<button>', { 'class': 'button pl-follow-up-btn', 'type': 'button', 'text': q } )
                );
            } );
        }

    } // end if $startBtn.length (admin demo)

} )( jQuery );
