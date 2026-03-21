/**
 * PédagoLens Course Workbench — Éditeur PowerPoint (v3.0)
 * Layout 3 colonnes : Filmstrip | Canvas | Panel IA
 * Navigation par slides, pas de scroll page.
 */
( function( $ ) {
'use strict';

if ( typeof plWorkbench === 'undefined' ) return;

var ajaxUrl    = plWorkbench.ajaxUrl;
var nonce      = plWorkbench.nonce;
var projectId  = plWorkbench.projectId;
var sections   = plWorkbench.sections || [];
var totalSlides = plWorkbench.totalSlides || sections.length;
var slideImages = plWorkbench.slideImages || [];

var currentSlideIndex = 0;
var autoSaveTimers = {};
var visualData = plWorkbench.visualData || [];
var viewMode = visualData.length > 0 ? 'visual' : 'text'; // 'visual' or 'text'

// Add editor mode class to body — hides sidebar, header, WP admin bar
$( 'body' ).addClass( 'pl-editor-mode' );
// Also hide WP admin bar immediately
$( '#wpadminbar' ).hide();
// Remove padding-top WP adds for admin bar
$( 'html' ).css( 'padding-top', '0' );

// =========================================================================
// AJAX helper
// =========================================================================
function ajax( action, data ) {
    return $.post( ajaxUrl, $.extend( { action: action, nonce: nonce, project_id: projectId }, data ) );
}

// =========================================================================
// SLIDE NAVIGATION
// =========================================================================
function showSlide( index ) {
    if ( index < 0 || index >= sections.length ) return;

    // Auto-save current slide before switching
    saveCurrentSlide();

    currentSlideIndex = index;
    var sec = sections[ index ];
    var slideVisual = visualData[ index ] || null;

    var $slide = $( '#pl-canvas-slide' );

    if ( viewMode === 'visual' && slideVisual && slideVisual.elements && slideVisual.elements.length > 0 ) {
        // VISUAL MODE — render positioned elements like a real PowerPoint slide
        $slide.html( renderVisualSlide( index, sec, slideVisual ) );
        // Fit the visual slide to the available canvas space
        fitVisualSlide();
    } else {
        // TEXT MODE — classic textarea editor
        var imgHtml = '';
        if ( sec.slide_image_url ) {
            imgHtml = '<div class="pl-canvas-slide-image"><img src="' + sec.slide_image_url + '" alt="Diapositive ' + ( sec.slide_num || index + 1 ) + '" /></div>';
        }
        $slide.html(
            '<div class="pl-canvas-slide-inner" data-section-id="' + sec.id + '" data-slide-num="' + ( sec.slide_num || 0 ) + '">' +
            imgHtml +
            '<h2 class="pl-canvas-slide-title">' + escHtml( sec.title ) + '</h2>' +
            '<textarea class="pl-section-content pl-canvas-textarea" data-section-id="' + sec.id + '" rows="12">' + escHtml( sec.content ) + '</textarea>' +
            '</div>'
        );
    }

    // Update counter
    $( '#pl-slide-counter' ).text( 'Diapositive ' + ( index + 1 ) + ' / ' + sections.length );

    // Update toolbar button data-section-id
    $( '#pl-canvas-suggestions-btn, #pl-canvas-history-btn, #pl-canvas-save-btn, #pl-canvas-undo-btn' )
        .attr( 'data-section-id', sec.id ).data( 'section-id', sec.id );
    $( '#pl-canvas-undo-btn' ).hide();
    $( '#pl-canvas-save-status' ).text( '' );

    // Update filmstrip active state
    $( '.pl-filmstrip-item' ).removeClass( 'pl-filmstrip-item--active' );
    $( '.pl-filmstrip-item[data-slide-index="' + index + '"]' ).addClass( 'pl-filmstrip-item--active' );

    // Scroll filmstrip to keep active visible
    var $filmstrip = $( '#pl-filmstrip-list' );
    var $active = $filmstrip.find( '.pl-filmstrip-item--active' );
    if ( $active.length ) {
        var top = $active.position().top;
        var fh = $filmstrip.height();
        if ( top < 0 || top > fh - 60 ) {
            $filmstrip.animate( { scrollTop: $filmstrip.scrollTop() + top - fh / 3 }, 200 );
        }
    }

    // Clear suggestions panel when switching slides
    $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-empty">Cliquez sur « Suggestions IA » pour obtenir des recommandations.</p>' );

    // Update view toggle button text
    updateViewToggleBtn();
}

// =========================================================================
// VISUAL SLIDE RENDERER — renders positioned elements like PowerPoint
// =========================================================================
function renderVisualSlide( index, sec, slideVisual ) {
    var sw = slideVisual.width || 960;
    var sh = slideVisual.height || 540;
    var bgColor = slideVisual.bg_color || '#ffffff';

    var html = '<div class="pl-visual-slide" data-section-id="' + sec.id + '" ' +
        'data-slide-num="' + ( sec.slide_num || index + 1 ) + '" ' +
        'style="width:' + sw + 'px;height:' + sh + 'px;background:' + bgColor + ';">';

    // Render each element
    var elements = slideVisual.elements || [];
    for ( var i = 0; i < elements.length; i++ ) {
        var el = elements[i];
        if ( el.type === 'image' ) {
            html += renderVisualImage( el );
        } else if ( el.type === 'text' ) {
            html += renderVisualText( el, i );
        }
    }

    // Slide number watermark
    html += '<div class="pl-visual-slide-num">' + ( index + 1 ) + '</div>';
    html += '</div>';

    return html;
}

function renderVisualImage( el ) {
    return '<img class="pl-visual-element pl-visual-image" ' +
        'src="' + el.src + '" ' +
        'style="left:' + el.x + 'px;top:' + el.y + 'px;width:' + el.w + 'px;height:' + el.h + 'px;" ' +
        'alt="Image" draggable="false" />';
}

function renderVisualText( el, elIndex ) {
    var style = 'left:' + el.x + 'px;top:' + el.y + 'px;width:' + el.w + 'px;height:' + el.h + 'px;';
    if ( el.fill ) {
        style += 'background:' + el.fill + ';';
    }

    var html = '<div class="pl-visual-element pl-visual-text" data-el-index="' + elIndex + '" style="' + style + '">';

    var paragraphs = el.paragraphs || [];
    for ( var p = 0; p < paragraphs.length; p++ ) {
        var para = paragraphs[p];
        var align = para.align || 'left';
        html += '<p class="pl-visual-para" style="text-align:' + align + ';">';

        var runs = para.runs || [];
        for ( var r = 0; r < runs.length; r++ ) {
            var run = runs[r];
            var spanStyle = 'font-size:' + ( run.size || 18 ) + 'pt;color:' + ( run.color || '#000000' ) + ';';
            if ( run.bold ) spanStyle += 'font-weight:700;';
            if ( run.italic ) spanStyle += 'font-style:italic;';
            html += '<span class="pl-visual-run" style="' + spanStyle + '">' + escHtml( run.text ) + '</span>';
        }

        html += '</p>';
    }

    html += '</div>';
    return html;
}

// =========================================================================
// FIT VISUAL SLIDE — scale to fit the canvas area
// =========================================================================
function fitVisualSlide() {
    var $canvas = $( '#pl-editor-canvas' );
    var $slide = $( '.pl-visual-slide' );
    if ( ! $slide.length ) return;

    var canvasW = $canvas.width() - 48; // padding
    var canvasH = $canvas.height() - 120; // nav + toolbar
    var slideW = parseInt( $slide.css( 'width' ) );
    var slideH = parseInt( $slide.css( 'height' ) );

    if ( slideW <= 0 || slideH <= 0 ) return;

    var scaleX = canvasW / slideW;
    var scaleY = canvasH / slideH;
    var scale = Math.min( scaleX, scaleY, 1.2 ); // Don't scale up beyond 1.2x

    $slide.css( 'transform', 'scale(' + scale + ')' );
    $slide.css( 'transform-origin', 'top center' );
}

// Refit on window resize
$( window ).on( 'resize', function() {
    if ( viewMode === 'visual' ) fitVisualSlide();
} );

// =========================================================================
// VIEW TOGGLE — switch between visual and text mode
// =========================================================================
$( '#pl-view-toggle' ).on( 'click', function() {
    if ( viewMode === 'visual' ) {
        viewMode = 'text';
    } else {
        viewMode = 'visual';
    }
    showSlide( currentSlideIndex );
} );

function updateViewToggleBtn() {
    var $btn = $( '#pl-view-toggle' );
    if ( viewMode === 'visual' ) {
        $btn.html(
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> ' +
            'Vue texte'
        );
    } else {
        $btn.html(
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg> ' +
            'Vue visuelle'
        );
    }
    // Hide toggle if no visual data
    if ( ! visualData.length ) $btn.hide();
}
updateViewToggleBtn();

// =========================================================================
// INLINE EDITING on visual text elements
// =========================================================================
$( document ).on( 'dblclick', '.pl-visual-text', function() {
    var $el = $( this );
    if ( $el.attr( 'contenteditable' ) === 'true' ) return;
    $el.attr( 'contenteditable', 'true' ).addClass( 'pl-visual-text--editing' ).focus();
} );

$( document ).on( 'blur', '.pl-visual-text[contenteditable="true"]', function() {
    var $el = $( this );
    $el.removeAttr( 'contenteditable' ).removeClass( 'pl-visual-text--editing' );

    // Sync edited text back to sections array
    syncVisualToText();
} );

function syncVisualToText() {
    // Collect all text from visual elements and update the section content
    var sec = sections[ currentSlideIndex ];
    if ( ! sec ) return;

    var texts = [];
    $( '.pl-visual-text' ).each( function() {
        var t = $( this ).text().trim();
        if ( t ) texts.push( t );
    } );

    var newContent = texts.join( '\n\n' );
    if ( newContent !== sec.content ) {
        sec.content = newContent;
        // Auto-save
        ajax( 'pl_save_section', { section_id: sec.id, content: newContent } );
        showCanvasStatus( '✓ Sauvegardé' );
        // Update filmstrip preview
        $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
            .text( newContent.substring( 0, 40 ) );
    }
}

function saveCurrentSlide() {
    var sec = sections[ currentSlideIndex ];
    if ( ! sec ) return;

    if ( viewMode === 'visual' ) {
        // In visual mode, sync text from visual elements
        syncVisualToText();
        return;
    }

    var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sec.id + '"]' );
    if ( ! $textarea.length ) return;
    var newContent = $textarea.val();
    if ( newContent !== sec.content ) {
        sec.content = newContent;
        ajax( 'pl_save_section', { section_id: sec.id, content: newContent } );
        // Update filmstrip preview
        $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
            .text( newContent.substring( 0, 40 ) );
    }
}

function escHtml( str ) {
    if ( ! str ) return '';
    return str.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
}

// =========================================================================
// FILMSTRIP CLICK
// =========================================================================
$( document ).on( 'click', '.pl-filmstrip-item', function() {
    var idx = parseInt( $( this ).data( 'slide-index' ), 10 );
    if ( ! isNaN( idx ) ) showSlide( idx );
} );

// =========================================================================
// PREV / NEXT BUTTONS
// =========================================================================
$( '#pl-slide-prev' ).on( 'click', function() {
    if ( currentSlideIndex > 0 ) showSlide( currentSlideIndex - 1 );
} );
$( '#pl-slide-next' ).on( 'click', function() {
    if ( currentSlideIndex < sections.length - 1 ) showSlide( currentSlideIndex + 1 );
} );

// =========================================================================
// KEYBOARD NAVIGATION
// =========================================================================
$( document ).on( 'keydown', function( e ) {
    // Don't navigate if typing in textarea or input
    if ( $( e.target ).is( 'textarea, input, [contenteditable]' ) ) return;
    if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
        e.preventDefault();
        if ( currentSlideIndex > 0 ) showSlide( currentSlideIndex - 1 );
    } else if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
        e.preventDefault();
        if ( currentSlideIndex < sections.length - 1 ) showSlide( currentSlideIndex + 1 );
    }
} );

// =========================================================================
// FILMSTRIP COLLAPSE / EXPAND
// =========================================================================
$( '#pl-filmstrip-toggle' ).on( 'click', function() {
    var $filmstrip = $( '#pl-filmstrip' );
    $filmstrip.toggleClass( 'pl-filmstrip-collapsed' );
    var collapsed = $filmstrip.hasClass( 'pl-filmstrip-collapsed' );
    $( this ).attr( 'title', collapsed ? 'Agrandir' : 'Réduire' );
    // Flip the chevron
    $( this ).find( 'svg' ).css( 'transform', collapsed ? 'rotate(180deg)' : 'rotate(0deg)' );
    try { localStorage.setItem( 'pl-filmstrip-collapsed', collapsed ? '1' : '0' ); } catch(e) {}
} );

// Restore filmstrip state
try {
    if ( localStorage.getItem( 'pl-filmstrip-collapsed' ) === '1' ) {
        $( '#pl-filmstrip' ).addClass( 'pl-filmstrip-collapsed' );
        $( '#pl-filmstrip-toggle' ).attr( 'title', 'Agrandir' ).find( 'svg' ).css( 'transform', 'rotate(180deg)' );
    }
} catch(e) {}


// =========================================================================
// SUGGESTIONS IA — inject into right panel
// =========================================================================
$( document ).on( 'click', '.pl-btn-suggestions, #pl-canvas-suggestions-btn', function() {
    var sectionId = $( this ).data( 'section-id' ) || sections[ currentSlideIndex ]?.id;
    if ( ! sectionId ) return;

    var $panel = $( '#pl-panel-suggestions' );
    $panel.html( '<div class="pl-panel-loading"><div class="pl-skeleton-loader"><div class="pl-skeleton-line pl-skeleton-line-lg"></div><div class="pl-skeleton-line pl-skeleton-line-md"></div><div class="pl-skeleton-line pl-skeleton-line-sm"></div></div></div>' );

    ajax( 'pl_get_suggestions', { section_id: sectionId } )
        .done( function( res ) {
            if ( res.success ) {
                $panel.html( res.data.html );
                if ( res.data.scores_html ) {
                    $( '#pl-sidebar-scores' ).html( res.data.scores_html );
                }
            } else {
                $panel.html( '<p class="pl-panel-error">✗ ' + ( res.data?.message || 'Erreur.' ) + '</p>' );
            }
        } )
        .fail( function() {
            $panel.html( '<p class="pl-panel-error">Erreur réseau.</p>' );
        } );
} );

// =========================================================================
// APPLY SUGGESTION — update canvas textarea
// =========================================================================
$( document ).on( 'click', '.pl-btn-apply', function() {
    var $btn = $( this );
    var sectionId = $btn.data( 'section-id' );
    var suggestionId = $btn.data( 'suggestion-id' );

    // Store previous content for undo
    var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
    if ( ! $textarea.length ) $textarea = $( '.pl-section-content[data-section-id="' + sectionId + '"]' );
    var prevContent = $textarea.val();
    $textarea.data( 'prev-content', prevContent );

    $btn.prop( 'disabled', true ).text( 'Application…' );

    ajax( 'pl_apply_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
        .done( function( res ) {
            if ( res.success ) {
                $textarea.val( res.data.new_content );
                // Update sections array
                for ( var i = 0; i < sections.length; i++ ) {
                    if ( sections[i].id === sectionId ) {
                        sections[i].content = res.data.new_content;
                        break;
                    }
                }
                flashCanvas();
                $btn.closest( '.pl-suggestion-card' ).fadeOut( 300 );
                $( '#pl-canvas-undo-btn' ).show();
            } else {
                alert( res.data?.message || 'Erreur.' );
                $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
            }
        } )
        .fail( function() {
            alert( 'Erreur réseau.' );
            $btn.prop( 'disabled', false ).text( '✓ Appliquer' );
        } );
} );

// =========================================================================
// REJECT SUGGESTION
// =========================================================================
$( document ).on( 'click', '.pl-btn-reject', function() {
    var $btn = $( this );
    var sectionId = $btn.data( 'section-id' );
    var suggestionId = $btn.data( 'suggestion-id' );
    ajax( 'pl_reject_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
        .done( function() { $btn.closest( '.pl-suggestion-card' ).fadeOut( 200 ); } );
} );

// =========================================================================
// SAVE SECTION (manual button)
// =========================================================================
$( document ).on( 'click', '.pl-btn-save-section, #pl-canvas-save-btn', function() {
    var sectionId = $( this ).data( 'section-id' ) || sections[ currentSlideIndex ]?.id;
    var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
    if ( ! $textarea.length ) $textarea = $( '.pl-section-content[data-section-id="' + sectionId + '"]' );
    var content = $textarea.val();

    $( this ).prop( 'disabled', true );
    var $btn = $( this );

    ajax( 'pl_save_section', { section_id: sectionId, content: content } )
        .done( function( res ) {
            if ( res.success ) {
                showCanvasStatus( '✓ Enregistré' );
                // Update sections array
                for ( var i = 0; i < sections.length; i++ ) {
                    if ( sections[i].id === sectionId ) { sections[i].content = content; break; }
                }
            } else {
                showCanvasStatus( '✗ Erreur', true );
            }
        } )
        .fail( function() { showCanvasStatus( '✗ Erreur réseau', true ); } )
        .always( function() { $btn.prop( 'disabled', false ); } );
} );

// =========================================================================
// AUTO-SAVE on textarea input (debounce 2s)
// =========================================================================
$( document ).on( 'input', '.pl-canvas-textarea, .pl-section-content', function() {
    var $textarea = $( this );
    var sectionId = $textarea.data( 'section-id' );
    if ( ! sectionId ) return;

    clearTimeout( autoSaveTimers[ sectionId ] );
    showCanvasStatus( '⏳ Sauvegarde...' );

    autoSaveTimers[ sectionId ] = setTimeout( function() {
        var content = $textarea.val();
        ajax( 'pl_save_section', { section_id: sectionId, content: content } )
            .done( function( res ) {
                if ( res.success ) {
                    showCanvasStatus( '✓ Sauvegardé' );
                    for ( var i = 0; i < sections.length; i++ ) {
                        if ( sections[i].id === sectionId ) { sections[i].content = content; break; }
                    }
                    // Update filmstrip preview
                    $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
                        .text( content.substring( 0, 40 ) );
                } else {
                    showCanvasStatus( '✗ Erreur', true );
                }
            } )
            .fail( function() { showCanvasStatus( '✗ Erreur réseau', true ); } );
    }, 2000 );
} );

// =========================================================================
// UNDO
// =========================================================================
$( document ).on( 'click', '#pl-canvas-undo-btn, .pl-btn-undo', function() {
    var sectionId = $( this ).data( 'section-id' ) || sections[ currentSlideIndex ]?.id;
    var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
    var prevContent = $textarea.data( 'prev-content' );
    if ( typeof prevContent !== 'undefined' ) {
        $textarea.val( prevContent );
        for ( var i = 0; i < sections.length; i++ ) {
            if ( sections[i].id === sectionId ) { sections[i].content = prevContent; break; }
        }
        ajax( 'pl_save_section', { section_id: sectionId, content: prevContent } );
        $( this ).hide();
        flashCanvas();
    }
} );


// =========================================================================
// HISTORY MODAL
// =========================================================================
$( document ).on( 'click', '.pl-btn-history, #pl-canvas-history-btn', function() {
    var sectionId = $( this ).data( 'section-id' ) || sections[ currentSlideIndex ]?.id;
    var $modal = $( '#pl-versions-modal' );
    var $content = $( '#pl-versions-content' );
    $content.html( '<p>Chargement…</p>' );
    $modal.show();
    ajax( 'pl_get_versions', { section_id: sectionId } )
        .done( function( res ) { $content.html( res.success ? res.data.html : '<p>Erreur.</p>' ); } );
} );
$( document ).on( 'click', '#pl-versions-close', function() { $( '#pl-versions-modal' ).hide(); } );

// =========================================================================
// ADD SECTION MODAL
// =========================================================================
$( '#pl-add-section' ).on( 'click', function() {
    $( '#pl-modal-add-section' ).fadeIn( 200 );
    $( '#pl-new-section-title' ).val( '' ).focus();
    $( '#pl-new-section-content' ).val( '' );
} );

// Close modals
$( document ).on( 'click', '.pl-stitch-modal-close, .pl-stitch-modal-cancel, .pl-stitch-modal-overlay', function() {
    $( this ).closest( '.pl-stitch-modal' ).fadeOut( 200 );
} );
$( document ).on( 'keydown', function( e ) {
    if ( e.key === 'Escape' ) $( '.pl-stitch-modal:visible' ).fadeOut( 200 );
} );

// Confirm add section
$( '#pl-confirm-add-section' ).on( 'click', function() {
    var title = $( '#pl-new-section-title' ).val().trim();
    if ( ! title ) { $( '#pl-new-section-title' ).focus(); return; }
    var content = $( '#pl-new-section-content' ).val().trim();
    var $btn = $( this );
    $btn.prop( 'disabled', true ).text( 'Ajout en cours…' );

    ajax( 'pl_add_section', { title: title, content: content, context: 'front' } )
        .done( function( res ) {
            if ( res.success ) {
                // Add to sections array
                var newSec = { id: res.data.section_id || 'section_' + Date.now(), title: title, content: content, slide_image_url: '', slide_num: 0 };
                sections.push( newSec );
                totalSlides = sections.length;

                // Add filmstrip item
                var idx = sections.length - 1;
                $( '#pl-filmstrip-list' ).append(
                    '<div class="pl-filmstrip-item" data-slide-index="' + idx + '" data-section-id="' + newSec.id + '">' +
                    '<span class="pl-filmstrip-num">' + ( idx + 1 ) + '</span>' +
                    '<div class="pl-filmstrip-info"><span class="pl-filmstrip-item-title">' + escHtml( title.substring(0, 30) ) + '</span>' +
                    '<span class="pl-filmstrip-item-preview">' + escHtml( content.substring(0, 40) ) + '</span></div></div>'
                );

                // Navigate to new slide
                showSlide( idx );
                $( '#pl-modal-add-section' ).fadeOut( 200 );
            }
        } )
        .always( function() {
            $btn.prop( 'disabled', false ).text( 'Ajouter la section' );
        } );
} );

$( '#pl-new-section-title' ).on( 'keydown', function( e ) {
    if ( e.key === 'Enter' ) { e.preventDefault(); $( '#pl-confirm-add-section' ).trigger( 'click' ); }
} );

// =========================================================================
// IMPORT MODAL + FILE UPLOAD
// =========================================================================
$( '#pl-upload-trigger' ).on( 'click', function() {
    $( '#pl-modal-import' ).fadeIn( 200 );
} );

var $dropzone = $( '#pl-dropzone' );
$dropzone.on( 'dragover dragenter', function( e ) { e.preventDefault(); e.stopPropagation(); $( this ).addClass( 'pl-drag-over' ); } );
$dropzone.on( 'dragleave drop', function( e ) { e.preventDefault(); e.stopPropagation(); $( this ).removeClass( 'pl-drag-over' ); } );
$dropzone.on( 'drop', function( e ) { var files = e.originalEvent.dataTransfer.files; if ( files.length ) handleFiles( files ); } );
$dropzone.on( 'click', function( e ) { if ( ! $( e.target ).is( 'label' ) && ! $( e.target ).closest( 'label' ).length ) $( '#pl-file-input' ).trigger( 'click' ); } );
$( '#pl-file-input' ).on( 'change', function() { if ( this.files.length ) { handleFiles( this.files ); this.value = ''; } } );

function handleFiles( files ) {
    var allowed = [ 'pptx', 'docx', 'pdf' ];
    var queue = [];
    for ( var i = 0; i < files.length; i++ ) {
        var ext = files[i].name.split('.').pop().toLowerCase();
        if ( allowed.indexOf( ext ) !== -1 ) queue.push( files[i] );
    }
    if ( ! queue.length ) { showUploadResult( 'Formats acceptés : .pptx, .docx, .pdf', true ); return; }
    uploadNext( queue, 0 );
}

function uploadNext( queue, index ) {
    if ( index >= queue.length ) { $( '#pl-upload-progress' ).fadeOut( 200 ); return; }
    var file = queue[ index ];
    var fd = new FormData();
    fd.append( 'action', 'pl_upload_file' );
    fd.append( 'nonce', nonce );
    fd.append( 'project_id', projectId );
    fd.append( 'file', file );

    $( '#pl-upload-progress' ).show();
    $( '#pl-upload-result' ).hide();
    $( '#pl-progress-text' ).text( file.name + '…' );
    $( '#pl-progress-bar' ).css( 'width', '0%' );

    $.ajax( {
        url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
        xhr: function() {
            var xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener( 'progress', function( e ) {
                if ( e.lengthComputable ) {
                    var pct = Math.round( ( e.loaded / e.total ) * 100 );
                    $( '#pl-progress-bar' ).css( 'width', pct + '%' );
                    $( '#pl-progress-text' ).text( file.name + ' — ' + pct + '%' );
                }
            } );
            return xhr;
        },
        success: function( res ) {
            if ( res.success ) {
                showUploadResult( '✓ ' + res.data.message, false );
                // Update visual data if available
                if ( res.data.visual_data && res.data.visual_data.length ) {
                    visualData = res.data.visual_data;
                    viewMode = 'visual';
                    updateViewToggleBtn();
                }
                // Reload page to get new sections (simplest approach)
                setTimeout( function() { location.reload(); }, 1000 );
            } else {
                showUploadResult( '✗ ' + ( res.data?.message || 'Erreur.' ), true );
            }
            uploadNext( queue, index + 1 );
        },
        error: function() { showUploadResult( '✗ Erreur réseau.', true ); uploadNext( queue, index + 1 ); }
    } );
}

function showUploadResult( msg, isError ) {
    $( '#pl-upload-result' ).text( msg ).css( {
        background: isError ? 'rgba(239,68,68,0.08)' : 'rgba(34,197,94,0.08)',
        borderColor: isError ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)',
        color: isError ? '#fca5a5' : '#4ade80'
    } ).show();
}


// =========================================================================
// ANALYZE ALL SECTIONS
// =========================================================================
$( '#pl-analyze-all' ).on( 'click', function() {
    var $btn = $( this );
    if ( ! sections.length ) { alert( 'Aucune section.' ); return; }

    $btn.prop( 'disabled', true ).html( '⏳ Analyse globale…' );
    $( '#pl-panel-suggestions' ).html(
        '<div class="pl-skeleton-loader"><div class="pl-skeleton-line pl-skeleton-line-lg"></div><div class="pl-skeleton-line pl-skeleton-line-md"></div><div class="pl-skeleton-line pl-skeleton-line-sm"></div></div>'
    );

    ajax( 'pl_analyze_all_sections', {} )
        .done( function( res ) {
            if ( res.success ) {
                // Show suggestions for current slide in panel
                var curId = sections[ currentSlideIndex ]?.id;
                var secs = res.data.sections || {};
                if ( curId && secs[ curId ] ) {
                    $( '#pl-panel-suggestions' ).html( secs[ curId ].html );
                } else {
                    // Show first available
                    var shown = false;
                    for ( var sid in secs ) {
                        if ( secs.hasOwnProperty( sid ) ) {
                            $( '#pl-panel-suggestions' ).html( secs[ sid ].html );
                            shown = true;
                            break;
                        }
                    }
                    if ( ! shown ) $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-empty">Aucune suggestion générée.</p>' );
                }
                if ( res.data.scores_html ) {
                    $( '#pl-sidebar-scores' ).html( res.data.scores_html );
                }
            } else {
                $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-error">' + ( res.data?.message || 'Erreur.' ) + '</p>' );
            }
        } )
        .fail( function() { $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-error">Erreur réseau.</p>' ); } )
        .always( function() { $btn.prop( 'disabled', false ).html( 'Analyser toutes les diapositives' ); } );
} );

// =========================================================================
// DOWNLOAD MODIFIED PPTX
// =========================================================================
$( '#pl-download-pptx' ).on( 'click', function() {
    var $btn = $( this );
    $btn.prop( 'disabled', true ).text( '⏳ Génération…' );
    ajax( 'pl_download_modified', {} )
        .done( function( res ) {
            if ( res.success && res.data.url ) {
                var a = document.createElement( 'a' );
                a.href = res.data.url;
                a.download = res.data.filename || 'modified.pptx';
                document.body.appendChild( a );
                a.click();
                document.body.removeChild( a );
            } else {
                alert( res.data?.message || 'Erreur.' );
            }
        } )
        .fail( function() { alert( 'Erreur réseau.' ); } )
        .always( function() { $btn.prop( 'disabled', false ).text( 'Télécharger PPTX' ); } );
} );

// Show download button if slides exist
if ( slideImages.length > 0 ) $( '#pl-download-pptx' ).show();

// =========================================================================
// PREVIEW MODAL
// =========================================================================
$( document ).on( 'click', '.pl-btn-preview', function() {
    var sectionId = $( this ).data( 'section-id' );
    var suggestionId = $( this ).data( 'suggestion-id' );
    var $modal = $( '#pl-preview-modal' );
    $modal.fadeIn( 200 );

    $( '#pl-preview-original' ).text( 'Chargement…' );
    $( '#pl-preview-proposed' ).text( '' );
    $( '#pl-preview-rationale' ).hide();
    $( '#pl-preview-slide-img' ).hide();
    $( '#pl-preview-apply' ).data( 'section-id', sectionId ).data( 'suggestion-id', suggestionId );

    ajax( 'pl_preview_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
        .done( function( res ) {
            if ( res.success ) {
                $( '#pl-preview-original' ).text( res.data.original );
                $( '#pl-preview-proposed' ).text( res.data.proposed );
                if ( res.data.rationale ) $( '#pl-preview-rationale' ).text( res.data.rationale ).show();
                if ( res.data.slide_image_url ) {
                    $( '#pl-preview-slide-img img' ).attr( 'src', res.data.slide_image_url );
                    $( '#pl-preview-slide-img' ).show();
                }
            }
        } );
} );

$( '#pl-preview-apply' ).on( 'click', function() {
    var $btn = $( this );
    var sectionId = $btn.data( 'section-id' );
    var suggestionId = $btn.data( 'suggestion-id' );
    $btn.prop( 'disabled', true ).text( 'Application…' );

    ajax( 'pl_apply_suggestion', { section_id: sectionId, suggestion_id: suggestionId } )
        .done( function( res ) {
            if ( res.success ) {
                $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' ).val( res.data.new_content );
                for ( var i = 0; i < sections.length; i++ ) {
                    if ( sections[i].id === sectionId ) { sections[i].content = res.data.new_content; break; }
                }
                flashCanvas();
                $( '#pl-sug-' + suggestionId ).fadeOut( 300 );
                $( '#pl-canvas-undo-btn' ).show();
                $( '#pl-preview-modal' ).fadeOut( 200 );
            }
        } )
        .always( function() { $btn.prop( 'disabled', false ).text( 'Appliquer cette suggestion' ); } );
} );

// =========================================================================
// SLIDE VIEWER (for slide images)
// =========================================================================
var slideViewerCurrent = 0;

function openSlideViewer( images, startIndex ) {
    slideImages = images;
    slideViewerCurrent = startIndex || 0;
    var modal = document.getElementById( 'pl-slide-viewer' );
    if ( ! modal ) return;
    modal.style.display = 'flex';
    updateSlideViewer();
    document.addEventListener( 'keydown', slideViewerKeyHandler );
}
function closeSlideViewer() {
    var modal = document.getElementById( 'pl-slide-viewer' );
    if ( modal ) modal.style.display = 'none';
    document.removeEventListener( 'keydown', slideViewerKeyHandler );
}
function updateSlideViewer() {
    var img = document.getElementById( 'pl-slide-viewer-img' );
    var counter = document.getElementById( 'pl-slide-viewer-counter' );
    if ( ! img || ! slideImages.length ) return;
    img.src = slideImages[ slideViewerCurrent ].url;
    if ( counter ) counter.textContent = 'Diapositive ' + ( slideViewerCurrent + 1 ) + ' / ' + slideImages.length;
}
function slideViewerKeyHandler( e ) {
    if ( e.key === 'ArrowRight' ) slideViewerNext();
    else if ( e.key === 'ArrowLeft' ) slideViewerPrev();
    else if ( e.key === 'Escape' ) closeSlideViewer();
}
function slideViewerNext() { if ( slideViewerCurrent < slideImages.length - 1 ) { slideViewerCurrent++; updateSlideViewer(); } }
function slideViewerPrev() { if ( slideViewerCurrent > 0 ) { slideViewerCurrent--; updateSlideViewer(); } }

// Make global for onclick handlers
window.openSlideViewer = openSlideViewer;
window.closeSlideViewer = closeSlideViewer;
window.slideViewerNext = slideViewerNext;
window.slideViewerPrev = slideViewerPrev;

// =========================================================================
// HELPERS
// =========================================================================
function flashCanvas() {
    var $slide = $( '#pl-canvas-slide' );
    $slide.addClass( 'pl-canvas-flash' );
    setTimeout( function() { $slide.removeClass( 'pl-canvas-flash' ); }, 1200 );
}

function showCanvasStatus( msg, isError ) {
    var $status = $( '#pl-canvas-save-status' );
    $status.text( msg ).css( 'color', isError ? '#f87171' : '#4ade80' );
    if ( msg.indexOf( '⏳' ) === -1 ) {
        setTimeout( function() { $status.fadeOut( 300, function() { $( this ).text( '' ).show(); } ); }, 3000 );
    }
}

// =========================================================================
// SUGGESTION HOVER → highlight filmstrip item
// =========================================================================
$( document ).on( 'mouseenter', '.pl-suggestion-card[data-section-id]', function() {
    var sid = $( this ).data( 'section-id' );
    $( '.pl-filmstrip-item' ).each( function() {
        if ( $( this ).data( 'section-id' ) === sid ) $( this ).addClass( 'pl-filmstrip-item--highlight' );
    } );
} );
$( document ).on( 'mouseleave', '.pl-suggestion-card[data-section-id]', function() {
    $( '.pl-filmstrip-item' ).removeClass( 'pl-filmstrip-item--highlight' );
} );

// =========================================================================
// INIT — show first slide
// =========================================================================
if ( sections.length > 0 ) {
    // Already rendered by PHP, just ensure filmstrip is correct
    $( '#pl-slide-counter' ).text( 'Diapositive 1 / ' + sections.length );
}

} )( jQuery );
