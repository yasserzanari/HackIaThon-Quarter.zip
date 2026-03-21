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


// =============================================================================
// SLIDE VIEWER (Tâche 38.4)
// =============================================================================
var slideViewerImages = [];
var slideViewerCurrent = 0;

function openSlideViewer( images, startIndex ) {
    slideViewerImages = images;
    slideViewerCurrent = startIndex || 0;
    var modal = document.getElementById('pl-slide-viewer');
    if (!modal) return;
    modal.style.display = 'flex';
    updateSlideViewer();
    document.addEventListener('keydown', slideViewerKeyHandler);
}

function closeSlideViewer() {
    var modal = document.getElementById('pl-slide-viewer');
    if (modal) modal.style.display = 'none';
    document.removeEventListener('keydown', slideViewerKeyHandler);
}

function updateSlideViewer() {
    var img = document.getElementById('pl-slide-viewer-img');
    var counter = document.getElementById('pl-slide-viewer-counter');
    if (!img || !slideViewerImages.length) return;
    img.src = slideViewerImages[slideViewerCurrent].url;
    if (counter) counter.textContent = 'Diapositive ' + (slideViewerCurrent + 1) + ' / ' + slideViewerImages.length;
}

function slideViewerKeyHandler(e) {
    if (e.key === 'ArrowRight') { slideViewerNext(); }
    else if (e.key === 'ArrowLeft') { slideViewerPrev(); }
    else if (e.key === 'Escape') { closeSlideViewer(); }
}

function slideViewerNext() {
    if (slideViewerCurrent < slideViewerImages.length - 1) {
        slideViewerCurrent++;
        updateSlideViewer();
    }
}

function slideViewerPrev() {
    if (slideViewerCurrent > 0) {
        slideViewerCurrent--;
        updateSlideViewer();
    }
}

// Click on slide thumbnails to open viewer
( function( $ ) {
    'use strict';

    var ajaxUrl   = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.ajaxUrl : '';
    var nonce     = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.nonce   : '';
    var projectId = ( typeof plWorkbench !== 'undefined' ) ? plWorkbench.projectId : 0;
    var slideImages = ( typeof plWorkbench !== 'undefined' && plWorkbench.slideImages ) ? plWorkbench.slideImages : [];

    var ajax = function( action, data ) {
        return $.post( ajaxUrl, { action: action, nonce: nonce, project_id: projectId, ...data } );
    };

    // Show download button if PPTX slides exist
    if ( slideImages.length > 0 ) {
        $( '#pl-download-pptx' ).show();
    }

    // =========================================================================
    // Slide thumbnail click → open viewer
    // =========================================================================
    $( document ).on( 'click', '.pl-stitch-wb-slide-thumb', function() {
        var idx = parseInt( $( this ).data('slide-index'), 10 ) || 0;
        if ( slideImages.length ) {
            openSlideViewer( slideImages, idx );
        }
    } );

    // Update slideImages when upload returns new ones
    $( document ).on( 'pl:slideImagesUpdated', function( e, images ) {
        slideImages = images;
        if ( images.length > 0 ) {
            $( '#pl-download-pptx' ).show();
        }
    } );

    // =========================================================================
    // ENRICHED SUGGESTIONS — staggered animation + section highlight (Tâche 39.4)
    // =========================================================================
    $( document ).on( 'mouseenter', '.pl-suggestion-card[data-section-id]', function() {
        var sectionId = $( this ).data('section-id');
        $( '#pl-section-' + sectionId ).addClass('pl-section-highlighted');
    } );

    $( document ).on( 'mouseleave', '.pl-suggestion-card[data-section-id]', function() {
        var sectionId = $( this ).data('section-id');
        $( '#pl-section-' + sectionId ).removeClass('pl-section-highlighted');
    } );

    // =========================================================================
    // PREVIEW MODAL (Tâche 40.4)
    // =========================================================================
    $( document ).on( 'click', '.pl-btn-preview', function() {
        var sectionId    = $( this ).data('section-id');
        var suggestionId = $( this ).data('suggestion-id');
        openPreviewModal( suggestionId, sectionId );
    } );

    function openPreviewModal( suggestionId, sectionId ) {
        var $modal = $( '#pl-preview-modal' );
        $modal.fadeIn( 200 );

        // Reset
        $( '#pl-preview-original' ).text( 'Chargement…' );
        $( '#pl-preview-proposed' ).text( '' );
        $( '#pl-preview-rationale' ).hide();
        $( '#pl-preview-slide-img' ).hide();
        $( '#pl-preview-apply' ).data( 'section-id', sectionId ).data( 'suggestion-id', suggestionId );

        ajax( 'pl_preview_suggestion', {
            section_id: sectionId,
            suggestion_id: suggestionId
        } ).done( function( res ) {
            if ( res.success ) {
                $( '#pl-preview-original' ).text( res.data.original );
                $( '#pl-preview-proposed' ).text( res.data.proposed );
                if ( res.data.rationale ) {
                    $( '#pl-preview-rationale' ).text( res.data.rationale ).show();
                }
                if ( res.data.slide_image_url ) {
                    $( '#pl-preview-slide-img img' ).attr( 'src', res.data.slide_image_url );
                    $( '#pl-preview-slide-img' ).show();
                }
            } else {
                $( '#pl-preview-original' ).text( res.data?.message || 'Erreur.' );
            }
        } );
    }

    // Apply from preview modal
    $( '#pl-preview-apply' ).on( 'click', function() {
        var $btn         = $( this );
        var sectionId    = $btn.data('section-id');
        var suggestionId = $btn.data('suggestion-id');

        $btn.prop( 'disabled', true ).text( 'Application…' );

        ajax( 'pl_apply_suggestion', {
            section_id: sectionId,
            suggestion_id: suggestionId
        } ).done( function( res ) {
            if ( res.success ) {
                $( '.pl-section-content[data-section-id="' + sectionId + '"]' ).val( res.data.new_content );
                flashSection( sectionId );
                $( '#pl-sug-' + suggestionId ).fadeOut( 300 );
                showUndoButton( sectionId );
                $( '#pl-preview-modal' ).fadeOut( 200 );
            }
        } ).always( function() {
            $btn.prop( 'disabled', false ).html(
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Appliquer cette suggestion'
            );
        } );
    } );

    // =========================================================================
    // APPLY SUGGESTION — enhanced with flash + undo (Tâche 41)
    // =========================================================================
    // Override the existing apply handler with enhanced version
    $( document ).off( 'click', '.pl-btn-apply' ).on( 'click', '.pl-btn-apply', function() {
        var $btn         = $( this );
        var sectionId    = $btn.data('section-id');
        var suggestionId = $btn.data('suggestion-id');

        // Store previous content for undo
        var $textarea    = $( '.pl-section-content[data-section-id="' + sectionId + '"]' );
        var prevContent  = $textarea.val();
        $textarea.data( 'prev-content', prevContent );

        $btn.prop( 'disabled', true ).text( 'Application…' );

        ajax( 'pl_apply_suggestion', {
            section_id: sectionId,
            suggestion_id: suggestionId
        } ).done( function( res ) {
            if ( res.success ) {
                $textarea.val( res.data.new_content );
                flashSection( sectionId );
                $btn.closest( '.pl-suggestion-card' ).fadeOut( 300 );
                showUndoButton( sectionId );
            } else {
                alert( res.data?.message || 'Erreur.' );
                $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
            }
        } ).fail( function() {
            alert( 'Erreur réseau.' );
            $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
        } );
    } );

    function flashSection( sectionId ) {
        var $section = $( '#pl-section-' + sectionId );
        $section.addClass( 'pl-section-flash-green' );
        setTimeout( function() {
            $section.removeClass( 'pl-section-flash-green' );
        }, 1200 );
    }

    function showUndoButton( sectionId ) {
        $( '#pl-section-' + sectionId + ' .pl-btn-undo' ).show();
    }

    // Undo button
    $( document ).on( 'click', '.pl-btn-undo', function() {
        var sectionId   = $( this ).data('section-id');
        var $textarea   = $( '.pl-section-content[data-section-id="' + sectionId + '"]' );
        var prevContent = $textarea.data('prev-content');

        if ( typeof prevContent !== 'undefined' ) {
            $textarea.val( prevContent );
            // Save the reverted content
            ajax( 'pl_save_section', { section_id: sectionId, content: prevContent } );
            $( this ).hide();
            flashSection( sectionId );
        }
    } );

    // =========================================================================
    // DOWNLOAD MODIFIED PPTX (Tâche 42)
    // =========================================================================
    $( '#pl-download-pptx' ).on( 'click', function() {
        var $btn = $( this );
        $btn.prop( 'disabled', true ).text( '⏳ Génération…' );

        ajax( 'pl_download_modified', {} )
            .done( function( res ) {
                if ( res.success && res.data.url ) {
                    // Trigger download
                    var a = document.createElement('a');
                    a.href = res.data.url;
                    a.download = res.data.filename || 'modified.pptx';
                    document.body.appendChild( a );
                    a.click();
                    document.body.removeChild( a );
                } else {
                    alert( res.data?.message || 'Erreur lors de la génération.' );
                }
            } )
            .fail( function() {
                alert( 'Erreur réseau.' );
            } )
            .always( function() {
                $btn.prop( 'disabled', false ).html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Télécharger le PPTX modifié'
                );
            } );
    } );

    // =========================================================================
    // ANALYZE ALL SECTIONS — enhanced with skeleton loader (Tâche 43)
    // =========================================================================
    $( document ).off( 'click', '#pl-analyze-all' ).on( 'click', '#pl-analyze-all', function() {
        var $btn = $( this );
        var $sections = $( '.pl-stitch-wb-section' );

        if ( ! $sections.length ) {
            alert( 'Aucune section à analyser.' );
            return;
        }

        $btn.prop( 'disabled', true ).html(
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="pl-spin"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Analyse globale en cours…'
        );

        // Show skeleton loaders in each suggestion zone
        $sections.each( function() {
            var sid = $( this ).data('section-id');
            var $zone = $( '#pl-suggestions-' + sid );
            $zone.html(
                '<div class="pl-skeleton-loader">' +
                '<div class="pl-skeleton-line pl-skeleton-line-lg"></div>' +
                '<div class="pl-skeleton-line pl-skeleton-line-md"></div>' +
                '<div class="pl-skeleton-line pl-skeleton-line-sm"></div>' +
                '</div>'
            ).slideDown( 200 );
        } );

        ajax( 'pl_analyze_all_sections', {} )
            .done( function( res ) {
                if ( res.success ) {
                    // Inject suggestions per section
                    var sections = res.data.sections || {};
                    for ( var sid in sections ) {
                        if ( sections.hasOwnProperty( sid ) ) {
                            $( '#pl-suggestions-' + sid ).html( sections[ sid ].html ).slideDown( 200 );
                        }
                    }
                    // Update scores
                    if ( res.data.scores_html ) {
                        $( '#pl-sidebar-scores' ).html( res.data.scores_html );
                    }
                } else {
                    alert( res.data?.message || 'Erreur lors de l\'analyse.' );
                }
            } )
            .fail( function() {
                alert( 'Erreur réseau.' );
            } )
            .always( function() {
                $btn.prop( 'disabled', false ).html(
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg> Demander de nouvelles suggestions'
                );
                // Remove any remaining skeletons
                $( '.pl-skeleton-loader' ).remove();
            } );
    } );

    // =========================================================================
    // Update slideImages on upload success
    // =========================================================================
    var origUploadSuccess = null;
    $( document ).ajaxComplete( function( event, xhr, settings ) {
        if ( settings.data && typeof settings.data === 'object' ) return;
        if ( typeof settings.data === 'string' && settings.data.indexOf('pl_upload_file') !== -1 ) {
            try {
                var res = xhr.responseJSON;
                if ( res && res.success && res.data && res.data.slide_images && res.data.slide_images.length ) {
                    slideImages = res.data.slide_images;
                    $( '#pl-download-pptx' ).show();
                }
            } catch(e) {}
        }
    } );

} )( jQuery );
