/**
 * PédagoLens Course Workbench — Front JS (WOW Refonte)
 */
( function ( $ ) {
    'use strict';

    const { ajaxUrl, nonce, projectId } = plWorkbench;

    const ajax = ( action, data ) =>
        $.post( ajaxUrl, { action, nonce, project_id: projectId, ...data } );

    // -------------------------------------------------------------------------
    // Suggestions IA
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.pl-btn-suggestions', function () {
        const $btn      = $( this );
        const sectionId = $btn.data( 'section-id' );
        const $zone     = $( '#pl-suggestions-' + sectionId );

        if ( $zone.is( ':visible' ) ) {
            $zone.slideUp( 200 );
            return;
        }

        $zone.html( '<p class="pl-loading">⏳ Chargement des suggestions…</p>' ).slideDown( 200 );
        $btn.prop( 'disabled', true );

        ajax( 'pl_get_suggestions', { section_id: sectionId } )
            .done( res => {
                if ( res.success ) {
                    $zone.html( res.data.html );
                    if ( res.data.scores_html ) {
                        $( '#pl-sidebar-scores' ).html( res.data.scores_html );
                    }
                } else {
                    $zone.html( '<p class="pl-error">✗ ' + ( res.data?.message || 'Erreur.' ) + '</p>' );
                }
            } )
            .fail( () => $zone.html( '<p class="pl-error">Erreur réseau.</p>' ) )
            .always( () => $btn.prop( 'disabled', false ) );
    } );

    // -------------------------------------------------------------------------
    // Appliquer une suggestion
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.pl-btn-apply', function () {
        const $btn        = $( this );
        const sectionId   = $btn.data( 'section-id' );
        const suggestionId = $btn.data( 'suggestion-id' );

        $btn.prop( 'disabled', true ).text( 'Application…' );

        ajax( 'pl_apply_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
            .done( res => {
                if ( res.success ) {
                    $( `.pl-section-content[data-section-id="${sectionId}"]` ).val( res.data.new_content );
                    $btn.closest( '.pl-suggestion-card' ).fadeOut( 300 );
                    showStatus( sectionId, '✓ Suggestion appliquée' );
                } else {
                    alert( res.data?.message || 'Erreur.' );
                    $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
                }
            } )
            .fail( () => {
                alert( 'Erreur réseau.' );
                $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
            } );
    } );

    // -------------------------------------------------------------------------
    // Rejeter une suggestion
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.pl-btn-reject', function () {
        const $btn        = $( this );
        const sectionId   = $btn.data( 'section-id' );
        const suggestionId = $btn.data( 'suggestion-id' );

        ajax( 'pl_reject_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
            .done( () => $btn.closest( '.pl-suggestion-card' ).fadeOut( 200 ) );
    } );

    // -------------------------------------------------------------------------
    // Sauvegarder une section (bouton manuel)
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.pl-btn-save-section', function () {
        const $btn      = $( this );
        const sectionId = $btn.data( 'section-id' );
        const content   = $( `.pl-section-content[data-section-id="${sectionId}"]` ).val();

        $btn.prop( 'disabled', true );

        ajax( 'pl_save_section', { section_id: sectionId, content } )
            .done( res => {
                if ( res.success ) {
                    showStatus( sectionId, '✓ Enregistré' );
                } else {
                    showStatus( sectionId, '✗ Erreur', true );
                }
            } )
            .fail( () => showStatus( sectionId, '✗ Erreur réseau', true ) )
            .always( () => $btn.prop( 'disabled', false ) );
    } );

    // -------------------------------------------------------------------------
    // Auto-save sur les textareas (debounce 2s)
    // -------------------------------------------------------------------------
    var autoSaveTimers = {};
    $( document ).on( 'input', '.pl-section-content, .pl-stitch-wb-textarea', function () {
        var $textarea = $( this );
        var sectionId = $textarea.data( 'section-id' );
        if ( ! sectionId ) return;

        clearTimeout( autoSaveTimers[ sectionId ] );
        showStatus( sectionId, '⏳ Sauvegarde...', false );

        autoSaveTimers[ sectionId ] = setTimeout( function () {
            var content = $textarea.val();
            ajax( 'pl_save_section', { section_id: sectionId, content: content } )
                .done( function ( res ) {
                    if ( res.success ) {
                        showStatus( sectionId, '✓ Sauvegardé automatiquement' );
                    } else {
                        showStatus( sectionId, '✗ Erreur de sauvegarde', true );
                    }
                } )
                .fail( function () {
                    showStatus( sectionId, '✗ Erreur réseau', true );
                } );
        }, 2000 );
    } );

    // -------------------------------------------------------------------------
    // Historique des versions
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.pl-btn-history', function () {
        const sectionId = $( this ).data( 'section-id' );
        const $modal    = $( '#pl-versions-modal' );
        const $content  = $( '#pl-versions-content' );

        $content.html( '<p>Chargement…</p>' );
        $modal.show();

        ajax( 'pl_get_versions', { section_id: sectionId } )
            .done( res => {
                $content.html( res.success ? res.data.html : '<p>Erreur.</p>' );
            } );
    } );

    $( document ).on( 'click', '#pl-versions-close', () => $( '#pl-versions-modal' ).hide() );

    // -------------------------------------------------------------------------
    // Modal: Ajouter une section (remplace prompt())
    // -------------------------------------------------------------------------
    $( '#pl-add-section' ).on( 'click', function () {
        $( '#pl-modal-add-section' ).fadeIn( 200 );
        $( '#pl-new-section-title' ).val( '' ).focus();
        $( '#pl-new-section-content' ).val( '' );
    } );

    // Close any stitch modal
    $( document ).on( 'click', '.pl-stitch-modal-close, .pl-stitch-modal-cancel, .pl-stitch-modal-overlay', function () {
        $( this ).closest( '.pl-stitch-modal' ).fadeOut( 200 );
    } );

    // Escape key closes modals
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) {
            $( '.pl-stitch-modal:visible' ).fadeOut( 200 );
        }
    } );

    // Confirm add section
    $( '#pl-confirm-add-section' ).on( 'click', function () {
        var title = $( '#pl-new-section-title' ).val().trim();
        if ( ! title ) {
            $( '#pl-new-section-title' ).focus();
            return;
        }
        var content = $( '#pl-new-section-content' ).val().trim();
        var $btn = $( this );
        $btn.prop( 'disabled', true ).text( 'Ajout en cours…' );

        ajax( 'pl_add_section', { title: title, content: content, context: 'front' } )
            .done( function ( res ) {
                if ( res.success ) {
                    $( '.pl-stitch-wb-empty' ).hide();
                    $( '.pl-wb-main' ).append( res.data.html );
                    $( '#pl-modal-add-section' ).fadeOut( 200 );
                }
            } )
            .always( function () {
                $btn.prop( 'disabled', false ).html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Ajouter la section'
                );
            } );
    } );

    // Enter key in title field triggers add
    $( '#pl-new-section-title' ).on( 'keydown', function ( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            $( '#pl-confirm-add-section' ).trigger( 'click' );
        }
    } );

    // -------------------------------------------------------------------------
    // Helper : message de statut inline
    // -------------------------------------------------------------------------
    function showStatus( sectionId, msg, isError ) {
        isError = isError || false;
        const $status = $( '#pl-section-' + sectionId + ' .pl-save-status' );
        $status.text( msg ).css( 'color', isError ? '#f87171' : '#4ade80' );
        if ( msg.indexOf( '⏳' ) === -1 ) {
            setTimeout( function () { $status.fadeOut( 300, function () { $( this ).text( '' ).show(); } ); }, 3000 );
        }
    }

} )( jQuery );


// =============================================================================
// FRONT-END: File Upload (drag & drop + browse) — via Import Modal
// =============================================================================
( function ( $ ) {
    'use strict';

    var ajaxUrl   = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.ajaxUrl : '';
    var nonce     = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.nonce   : '';
    var projectId = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.projectId : 0;

    if ( ! ajaxUrl ) return;

    // -------------------------------------------------------------------------
    // Open import modal instead of slideToggle
    // -------------------------------------------------------------------------
    $( '#pl-upload-trigger' ).on( 'click', function () {
        $( '#pl-modal-import' ).fadeIn( 200 );
    } );

    // -------------------------------------------------------------------------
    // Drag & drop
    // -------------------------------------------------------------------------
    var $dropzone = $( '#pl-dropzone' );

    $dropzone.on( 'dragover dragenter', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        $( this ).addClass( 'pl-drag-over' );
    } );

    $dropzone.on( 'dragleave drop', function ( e ) {
        e.preventDefault();
        e.stopPropagation();
        $( this ).removeClass( 'pl-drag-over' );
    } );

    $dropzone.on( 'drop', function ( e ) {
        var files = e.originalEvent.dataTransfer.files;
        if ( files.length ) {
            handleFiles( files );
        }
    } );

    // Click to browse
    $dropzone.on( 'click', function ( e ) {
        if ( $( e.target ).is( 'label' ) || $( e.target ).closest( 'label' ).length ) return;
        $( '#pl-file-input' ).trigger( 'click' );
    } );

    $( '#pl-file-input' ).on( 'change', function () {
        if ( this.files.length ) {
            handleFiles( this.files );
            this.value = '';
        }
    } );

    // -------------------------------------------------------------------------
    // Handle file upload
    // -------------------------------------------------------------------------
    function handleFiles( files ) {
        var allowed = [ 'pptx', 'docx', 'pdf' ];
        var queue   = [];

        for ( var i = 0; i < files.length; i++ ) {
            var ext = files[ i ].name.split( '.' ).pop().toLowerCase();
            if ( allowed.indexOf( ext ) !== -1 ) {
                queue.push( files[ i ] );
            }
        }

        if ( ! queue.length ) {
            showUploadResult( 'Aucun fichier valide. Formats acceptés : .pptx, .docx, .pdf', true );
            return;
        }

        uploadNext( queue, 0 );
    }

    function uploadNext( queue, index ) {
        if ( index >= queue.length ) {
            $( '#pl-upload-progress' ).fadeOut( 200 );
            return;
        }

        var file = queue[ index ];
        var fd   = new FormData();
        fd.append( 'action', 'pl_upload_file' );
        fd.append( 'nonce', nonce );
        fd.append( 'project_id', projectId );
        fd.append( 'file', file );

        $( '#pl-upload-progress' ).show();
        $( '#pl-upload-result' ).hide();
        $( '#pl-progress-text' ).text( 'Téléversement de ' + file.name + '…' );
        $( '#pl-progress-bar' ).css( 'width', '0%' );

        $.ajax( {
            url: ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener( 'progress', function ( e ) {
                    if ( e.lengthComputable ) {
                        var pct = Math.round( ( e.loaded / e.total ) * 100 );
                        $( '#pl-progress-bar' ).css( 'width', pct + '%' );
                        $( '#pl-progress-text' ).text( file.name + ' — ' + pct + '%' );
                    }
                } );
                return xhr;
            },
            success: function ( res ) {
                if ( res.success ) {
                    showUploadResult( '✓ ' + res.data.message, false );

                    if ( res.data.sections_html ) {
                        $( '.pl-wb-main' ).append( res.data.sections_html );
                        $( '.pl-stitch-wb-empty' ).hide();
                    }

                    if ( res.data.file_html ) {
                        var $list = $( '#pl-files-list' );
                        $list.find( '.pl-stitch-wb-sidebar-empty' ).remove();
                        $list.append( res.data.file_html );
                    }
                } else {
                    showUploadResult( '✗ ' + ( res.data?.message || 'Erreur.' ), true );
                }

                uploadNext( queue, index + 1 );
            },
            error: function () {
                showUploadResult( '✗ Erreur réseau lors du téléversement.', true );
                uploadNext( queue, index + 1 );
            }
        } );
    }

    function showUploadResult( msg, isError ) {
        var $el = $( '#pl-upload-result' );
        $el.text( msg )
           .css( {
               background: isError ? 'rgba(239,68,68,0.08)' : 'rgba(34,197,94,0.08)',
               borderColor: isError ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)',
               color: isError ? '#fca5a5' : '#4ade80'
           } )
           .show();
    }

    // -------------------------------------------------------------------------
    // Analyze all sections
    // -------------------------------------------------------------------------
    $( '#pl-analyze-all' ).on( 'click', function () {
        var $btn = $( this );
        var $sections = $( '.pl-btn-suggestions' );

        if ( ! $sections.length ) {
            alert( 'Aucune section à analyser.' );
            return;
        }

        $btn.prop( 'disabled', true ).text( '⏳ Analyse en cours…' );

        var index = 0;
        function analyzeNext() {
            if ( index >= $sections.length ) {
                $btn.prop( 'disabled', false ).html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg> Demander de nouvelles suggestions'
                );
                return;
            }
            var $s = $sections.eq( index );
            $s.trigger( 'click' );
            index++;
            setTimeout( analyzeNext, 1500 );
        }
        analyzeNext();
    } );

} )( jQuery );
