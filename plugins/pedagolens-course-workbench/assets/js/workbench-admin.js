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

// Cache of suggestion HTML per section id — persists across slide switches
var suggestionsHtmlCache = {};

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
        // TEXT MODE — rich contenteditable editor styled like a real slide
        // Check for slide image from slideImages array or section data
        var slideImg = ( slideImages[ index ] && slideImages[ index ].url ) ? slideImages[ index ].url : ( sec.slide_image_url || '' );

        // If we have a real slide image, show it as the primary view
        if ( slideImg ) {
            $slide.html(
                '<div class="pl-canvas-slide-inner pl-image-mode-slide" data-section-id="' + sec.id + '" data-slide-num="' + ( sec.slide_num || 0 ) + '">' +
                    '<div class="pl-canvas-slide-image-primary">' +
                        '<img src="' + slideImg + '" alt="Diapositive ' + ( sec.slide_num || index + 1 ) + '" />' +
                    '</div>' +
                    '<details class="pl-canvas-text-details">' +
                        '<summary class="pl-canvas-text-toggle">📝 Modifier le texte</summary>' +
                        '<textarea class="pl-section-content pl-canvas-textarea" data-section-id="' + sec.id + '" rows="6">' + escHtml( sec.content ) + '</textarea>' +
                    '</details>' +
                '</div>'
            );

            // Update counter, toolbar, filmstrip, etc. (fall through below)
        } else {

        var imgHtml = '';

        // Build a mini toolbar for text formatting
        var toolbarHtml =
            '<div class="pl-text-toolbar">' +
                '<button type="button" class="pl-text-toolbar-btn" data-cmd="bold" title="Gras (Ctrl+B)">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"/></svg>' +
                '</button>' +
                '<button type="button" class="pl-text-toolbar-btn" data-cmd="italic" title="Italique (Ctrl+I)">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="4" x2="10" y2="4"/><line x1="14" y1="20" x2="5" y2="20"/><line x1="15" y1="4" x2="9" y2="20"/></svg>' +
                '</button>' +
                '<span class="pl-text-toolbar-sep"></span>' +
                '<button type="button" class="pl-text-toolbar-btn" data-cmd="formatBlock" data-value="h2" title="Titre">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 12l4-4v8"/></svg>' +
                '</button>' +
                '<button type="button" class="pl-text-toolbar-btn" data-cmd="formatBlock" data-value="p" title="Paragraphe">' +
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="17" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="17" y1="18" x2="3" y2="18"/></svg>' +
                '</button>' +
            '</div>';

        // Convert plain text content to HTML paragraphs for the contenteditable
        var contentHtml = '';
        if ( sec.content ) {
            var lines = sec.content.split( /\n/ );
            for ( var li = 0; li < lines.length; li++ ) {
                var line = lines[li].trim();
                if ( line ) {
                    contentHtml += '<p>' + escHtml( line ) + '</p>';
                }
            }
        }
        if ( ! contentHtml ) contentHtml = '<p><br></p>';

        $slide.html(
            '<div class="pl-canvas-slide-inner pl-text-mode-slide" data-section-id="' + sec.id + '" data-slide-num="' + ( sec.slide_num || 0 ) + '">' +
            imgHtml +
            '<h2 class="pl-canvas-slide-title">' + escHtml( sec.title ) + '</h2>' +
            toolbarHtml +
            '<div class="pl-section-content pl-canvas-richtext" contenteditable="true" data-section-id="' + sec.id + '" spellcheck="true">' +
                contentHtml +
            '</div>' +
            // Hidden textarea for backward compat with save logic
            '<textarea class="pl-section-content pl-canvas-textarea pl-hidden-textarea" data-section-id="' + sec.id + '" rows="12" style="display:none;">' + escHtml( sec.content ) + '</textarea>' +
            '</div>'
        );
    } // end inner else (no slide image)
    } // end outer else (text mode)

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

    // Restore cached suggestions for this slide, or show default message
    var cachedHtml = suggestionsHtmlCache[ sec.id ] || '';
    if ( cachedHtml ) {
        $( '#pl-panel-suggestions' ).html( cachedHtml );
    } else {
        $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-empty">Cliquez sur « Suggestions IA » pour obtenir des recommandations.</p>' );
    }

    // Update view toggle button text
    updateViewToggleBtn();
}

// =========================================================================
// VISUAL SLIDE RENDERER — renders positioned elements like PowerPoint
// =========================================================================
function renderVisualSlide( index, sec, slideVisual ) {
    var sw = slideVisual.width || 960;
    var sh = slideVisual.height || 540;
    var bgColor = slideVisual.bg_color || '';

    // Build background style: use provided color, or a subtle gradient default
    var bgStyle = '';
    if ( bgColor ) {
        bgStyle = 'background:' + bgColor + ';';
    } else {
        bgStyle = 'background:linear-gradient(135deg, #ffffff 0%, #f8f9fc 100%);';
    }

    var html = '<div class="pl-visual-slide" data-section-id="' + sec.id + '" ' +
        'data-slide-num="' + ( sec.slide_num || index + 1 ) + '" ' +
        'style="width:' + sw + 'px;height:' + sh + 'px;' + bgStyle + '">';

    // Render each element
    var elements = slideVisual.elements || [];
    for ( var i = 0; i < elements.length; i++ ) {
        var el = elements[i];
        if ( el.type === 'image' ) {
            html += renderVisualImage( el, i );
        } else if ( el.type === 'text' ) {
            html += renderVisualText( el, i );
        }
    }

    // Slide number watermark
    html += '<div class="pl-visual-slide-num">' + ( index + 1 ) + '</div>';
    html += '</div>';

    return html;
}

function renderVisualImage( el, elIndex ) {
    return '<img class="pl-visual-element pl-visual-image" ' +
        'data-el-index="' + elIndex + '" ' +
        'src="' + el.src + '" ' +
        'style="left:' + el.x + 'px;top:' + el.y + 'px;width:' + el.w + 'px;height:' + el.h + 'px;" ' +
        'alt="Image" draggable="false" />';
}

function renderVisualText( el, elIndex ) {
    var style = 'left:' + el.x + 'px;top:' + el.y + 'px;width:' + el.w + 'px;height:' + el.h + 'px;';
    if ( el.fill ) {
        style += 'background:' + el.fill + ';';
    }

    // Inline contenteditable for direct editing (no double-click needed)
    var html = '<div class="pl-visual-element pl-visual-text" ' +
        'contenteditable="true" ' +
        'data-el-index="' + elIndex + '" ' +
        'style="' + style + '" ' +
        'spellcheck="false">';

    var paragraphs = el.paragraphs || [];
    for ( var p = 0; p < paragraphs.length; p++ ) {
        var para = paragraphs[p];
        var align = para.align || 'left';
        var paraStyle = 'text-align:' + align + ';';
        if ( para.line_spacing ) {
            paraStyle += 'line-height:' + para.line_spacing + ';';
        }
        if ( para.space_before ) {
            paraStyle += 'margin-top:' + para.space_before + 'px;';
        }
        if ( para.space_after ) {
            paraStyle += 'margin-bottom:' + para.space_after + 'px;';
        }
        html += '<p class="pl-visual-para" style="' + paraStyle + '">';

        var runs = para.runs || [];
        if ( runs.length === 0 ) {
            // Empty paragraph — keep a non-breaking space for editing
            html += '<span class="pl-visual-run">&nbsp;</span>';
        }
        for ( var r = 0; r < runs.length; r++ ) {
            var run = runs[r];
            var spanStyle = 'font-size:' + ( run.size || 18 ) + 'pt;color:' + ( run.color || '#000000' ) + ';';
            if ( run.bold ) spanStyle += 'font-weight:700;';
            if ( run.italic ) spanStyle += 'font-style:italic;';
            if ( run.underline ) spanStyle += 'text-decoration:underline;';
            if ( run.font ) spanStyle += "font-family:'" + run.font + "',sans-serif;";
            html += '<span class="pl-visual-run" style="' + spanStyle + '">' + escHtml( run.text ) + '</span>';
        }

        html += '</p>';
    }

    html += '</div>';
    return html;
}

// =========================================================================
// FIT VISUAL SLIDE — scale to fit the canvas area, centered
// =========================================================================
function fitVisualSlide() {
    var $canvas = $( '#pl-editor-canvas' );
    var $slide = $( '.pl-visual-slide' );
    if ( ! $slide.length ) return;

    var canvasW = $canvas.width() - 64; // padding
    var canvasH = $canvas.height() - 140; // nav + toolbar + margins
    var slideW = parseInt( $slide.css( 'width' ) );
    var slideH = parseInt( $slide.css( 'height' ) );

    if ( slideW <= 0 || slideH <= 0 ) return;

    var scaleX = canvasW / slideW;
    var scaleY = canvasH / slideH;
    var scale = Math.min( scaleX, scaleY, 1.0 ); // Don't scale up beyond 1x

    $slide.css( {
        'transform': 'scale(' + scale + ')',
        'transform-origin': 'top center',
        'margin': '0 auto'
    } );

    // Set the wrapper height to match scaled slide so layout doesn't collapse
    var $slideWrap = $slide.parent();
    if ( $slideWrap.is( '#pl-canvas-slide' ) ) {
        $slideWrap.css( 'min-height', ( slideH * scale ) + 'px' );
    }
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
// INLINE EDITING on visual text elements (always contenteditable)
// =========================================================================

// Track which element is being edited for visual feedback
$( document ).on( 'focus', '.pl-visual-text[contenteditable="true"]', function() {
    $( '.pl-visual-text' ).removeClass( 'pl-visual-text--editing' );
    $( this ).addClass( 'pl-visual-text--editing' );
} );

$( document ).on( 'blur', '.pl-visual-text[contenteditable="true"]', function() {
    $( this ).removeClass( 'pl-visual-text--editing' );
    // Sync on blur
    syncVisualToText();
} );

// Debounced auto-save on visual text input
var visualSaveTimer = null;
$( document ).on( 'input', '.pl-visual-text[contenteditable="true"]', function() {
    clearTimeout( visualSaveTimer );
    showCanvasStatus( '⏳ Sauvegarde...' );
    visualSaveTimer = setTimeout( function() {
        syncVisualToText();
    }, 1500 );
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
        ajax( 'pl_save_section', { section_id: sec.id, content: newContent } )
            .done( function( res ) {
                if ( res.success ) {
                    showCanvasStatus( '✓ Sauvegardé' );
                } else {
                    showCanvasStatus( '✗ Erreur', true );
                }
            } )
            .fail( function() {
                showCanvasStatus( '✗ Erreur réseau', true );
            } );
        // Update filmstrip preview
        $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
            .text( newContent.substring( 0, 40 ) );
    }
}

// =========================================================================
// TEXT MODE — toolbar commands + contenteditable sync
// =========================================================================
$( document ).on( 'click', '.pl-text-toolbar-btn', function( e ) {
    e.preventDefault();
    var cmd = $( this ).data( 'cmd' );
    var val = $( this ).data( 'value' ) || null;
    if ( cmd === 'formatBlock' && val ) {
        document.execCommand( 'formatBlock', false, '<' + val + '>' );
    } else {
        document.execCommand( cmd, false, val );
    }
    // Sync to hidden textarea
    syncRichtextToTextarea();
} );

// Sync contenteditable richtext → hidden textarea on input
var richtextSaveTimer = null;
$( document ).on( 'input', '.pl-canvas-richtext[contenteditable="true"]', function() {
    clearTimeout( richtextSaveTimer );
    showCanvasStatus( '⏳ Sauvegarde...' );
    richtextSaveTimer = setTimeout( function() {
        syncRichtextToTextarea();
    }, 1500 );
} );

$( document ).on( 'blur', '.pl-canvas-richtext[contenteditable="true"]', function() {
    syncRichtextToTextarea();
} );

function syncRichtextToTextarea() {
    var $richtext = $( '.pl-canvas-richtext[contenteditable="true"]' );
    if ( ! $richtext.length ) return;

    var sectionId = $richtext.data( 'section-id' );
    // Extract plain text from the contenteditable
    var newContent = $richtext.text().trim();
    // Also update the hidden textarea for backward compat
    var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
    if ( $textarea.length ) {
        $textarea.val( newContent );
    }

    // Update sections array
    for ( var i = 0; i < sections.length; i++ ) {
        if ( sections[i].id === sectionId ) {
            if ( sections[i].content !== newContent ) {
                sections[i].content = newContent;
                ajax( 'pl_save_section', { section_id: sectionId, content: newContent } )
                    .done( function( res ) {
                        if ( res.success ) {
                            showCanvasStatus( '✓ Sauvegardé' );
                        } else {
                            showCanvasStatus( '✗ Erreur', true );
                        }
                    } )
                    .fail( function() {
                        showCanvasStatus( '✗ Erreur réseau', true );
                    } );
                // Update filmstrip preview
                $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
                    .text( newContent.substring( 0, 40 ) );
            }
            break;
        }
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

    // Text mode: check for richtext contenteditable first
    var $richtext = $( '.pl-canvas-richtext[data-section-id="' + sec.id + '"]' );
    if ( $richtext.length ) {
        syncRichtextToTextarea();
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

    ajax( 'pl_get_suggestions', { section_id: sectionId, context: 'front' } )
        .done( function( res ) {
            if ( res.success ) {
                $panel.html( res.data.html );
                suggestionsHtmlCache[ sectionId ] = res.data.html;
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
    var prevContent = '';
    for ( var i = 0; i < sections.length; i++ ) {
        if ( sections[i].id === sectionId ) { prevContent = sections[i].content; break; }
    }

    // Extract proposed text from the suggestion card DOM as fallback for cache misses
    var proposedContent = $btn.closest( '.pl-suggestion-card' ).find( '.pl-sug-diff-new .pl-sug-diff-text' ).text() || '';

    $btn.prop( 'disabled', true ).text( 'Application…' );

    ajax( 'pl_apply_suggestion', { section_id: sectionId, suggestion_id: suggestionId, proposed_content: proposedContent } )
        .done( function( res ) {
            if ( res.success ) {
                // Update sections array
                for ( var i = 0; i < sections.length; i++ ) {
                    if ( sections[i].id === sectionId ) {
                        sections[i]._prev_content = prevContent;
                        sections[i].content = res.data.new_content;
                        break;
                    }
                }
                // Update the visible canvas elements
                var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
                if ( $textarea.length ) $textarea.val( res.data.new_content );
                var $richtext = $( '.pl-canvas-richtext[data-section-id="' + sectionId + '"]' );
                if ( $richtext.length ) {
                    var lines = ( res.data.new_content || '' ).split( /\n/ );
                    var html = '';
                    for ( var li = 0; li < lines.length; li++ ) {
                        var line = lines[li].trim();
                        if ( line ) html += '<p>' + escHtml( line ) + '</p>';
                    }
                    if ( ! html ) html = '<p><br></p>';
                    $richtext.html( html );
                }
                // Update filmstrip preview
                $( '.pl-filmstrip-item[data-slide-index="' + currentSlideIndex + '"] .pl-filmstrip-item-preview' )
                    .text( ( res.data.new_content || '' ).substring( 0, 40 ) );
                flashCanvas();
                $btn.closest( '.pl-suggestion-card' ).addClass( 'pl-sug-card--applied' );
                setTimeout( function() { $btn.closest( '.pl-suggestion-card' ).fadeOut( 300 ); }, 600 );
                $( '#pl-canvas-undo-btn' ).show().data( 'prev-content', prevContent ).data( 'section-id', sectionId );
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
    var $undoBtn = $( this );
    var sectionId = $undoBtn.data( 'section-id' ) || sections[ currentSlideIndex ]?.id;
    var prevContent = $undoBtn.data( 'prev-content' );
    if ( typeof prevContent === 'undefined' ) {
        // Fallback: check sections array
        for ( var i = 0; i < sections.length; i++ ) {
            if ( sections[i].id === sectionId && sections[i]._prev_content ) {
                prevContent = sections[i]._prev_content;
                break;
            }
        }
    }
    if ( typeof prevContent !== 'undefined' && prevContent !== null ) {
        // Update sections array
        for ( var i = 0; i < sections.length; i++ ) {
            if ( sections[i].id === sectionId ) { sections[i].content = prevContent; break; }
        }
        // Update visible elements
        var $textarea = $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' );
        if ( $textarea.length ) $textarea.val( prevContent );
        var $richtext = $( '.pl-canvas-richtext[data-section-id="' + sectionId + '"]' );
        if ( $richtext.length ) {
            var lines = prevContent.split( /\n/ );
            var html = '';
            for ( var li = 0; li < lines.length; li++ ) {
                var line = lines[li].trim();
                if ( line ) html += '<p>' + escHtml( line ) + '</p>';
            }
            if ( ! html ) html = '<p><br></p>';
            $richtext.html( html );
        }
        ajax( 'pl_save_section', { section_id: sectionId, content: prevContent } );
        $undoBtn.hide();
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
// ANALYZE ALL SECTIONS — Progressive analysis section by section
// =========================================================================
function buildAnalyzeOverlayHTML() {
    return '<div class="pl-analyze-overlay" id="pl-analyze-overlay">' +
        '<div class="pl-analyze-progress">' +
            '<div class="pl-analyze-circle-wrap">' +
                '<svg class="pl-analyze-circle" viewBox="0 0 120 120">' +
                    '<circle class="pl-analyze-circle-bg" cx="60" cy="60" r="52" />' +
                    '<circle class="pl-analyze-circle-fg" id="pl-analyze-arc" cx="60" cy="60" r="52" ' +
                        'stroke-dasharray="326.73" stroke-dashoffset="326.73" />' +
                '</svg>' +
                '<span class="pl-analyze-pct" id="pl-analyze-pct">0%</span>' +
            '</div>' +
            '<div class="pl-analyze-info">' +
                '<p class="pl-analyze-section-name" id="pl-analyze-section-name">Préparation…</p>' +
                '<p class="pl-analyze-counter" id="pl-analyze-counter">0 / ' + sections.length + ' sections</p>' +
                '<p class="pl-analyze-eta" id="pl-analyze-eta"></p>' +
            '</div>' +
            '<div class="pl-analyze-dots"><span></span><span></span><span></span></div>' +
        '</div>' +
    '</div>';
}

function updateAnalyzeProgress( done, total, sectionName, avgTime ) {
    var pct = Math.round( ( done / total ) * 100 );
    var circumference = 2 * Math.PI * 52; // 326.73
    var offset = circumference - ( circumference * pct / 100 );

    $( '#pl-analyze-arc' ).css( 'stroke-dashoffset', offset );
    $( '#pl-analyze-pct' ).text( pct + '%' );
    $( '#pl-analyze-counter' ).text( done + ' / ' + total + ' sections analysées' );
    $( '#pl-analyze-section-name' ).text( sectionName );

    if ( done > 0 && done < total && avgTime > 0 ) {
        var remaining = Math.ceil( ( total - done ) * avgTime / 1000 );
        var etaText = remaining >= 60
            ? Math.floor( remaining / 60 ) + ' min ' + ( remaining % 60 ) + ' s restantes'
            : remaining + ' s restantes';
        $( '#pl-analyze-eta' ).text( '⏱ ' + etaText );
    } else if ( done >= total ) {
        $( '#pl-analyze-eta' ).text( '' );
    }
}

function showAnalyzeSummary( totalSuggestions, totalSections, totalTime ) {
    var seconds = Math.round( totalTime / 1000 );
    var timeText = seconds >= 60
        ? Math.floor( seconds / 60 ) + ' min ' + ( seconds % 60 ) + ' s'
        : seconds + ' s';

    var $overlay = $( '#pl-analyze-overlay' );
    $overlay.find( '.pl-analyze-progress' ).addClass( 'pl-analyze-done' );
    $overlay.find( '.pl-analyze-progress' ).html(
        '<div class="pl-analyze-summary">' +
            '<div class="pl-analyze-summary-icon">✅</div>' +
            '<h3 class="pl-analyze-summary-title">Analyse terminée</h3>' +
            '<div class="pl-analyze-summary-stats">' +
                '<div class="pl-analyze-stat">' +
                    '<span class="pl-analyze-stat-value">' + totalSuggestions + '</span>' +
                    '<span class="pl-analyze-stat-label">suggestions</span>' +
                '</div>' +
                '<div class="pl-analyze-stat">' +
                    '<span class="pl-analyze-stat-value">' + totalSections + '</span>' +
                    '<span class="pl-analyze-stat-label">sections</span>' +
                '</div>' +
                '<div class="pl-analyze-stat">' +
                    '<span class="pl-analyze-stat-value">' + timeText + '</span>' +
                    '<span class="pl-analyze-stat-label">durée</span>' +
                '</div>' +
            '</div>' +
            '<button class="pl-stitch-btn pl-stitch-btn-primary pl-analyze-close-btn" id="pl-analyze-close">Fermer</button>' +
        '</div>'
    );
}

$( '#pl-analyze-all' ).on( 'click', function() {
    var $btn = $( this );
    if ( ! sections.length ) { return; }

    $btn.prop( 'disabled', true );

    // Inject overlay
    $( 'body' ).append( buildAnalyzeOverlayHTML() );
    // Force reflow then show
    setTimeout( function() { $( '#pl-analyze-overlay' ).addClass( 'pl-analyze-overlay--visible' ); }, 20 );

    var total = sections.length;
    var done = 0;
    var totalSuggestions = 0;
    var allResults = {};
    var latestScoresHtml = '';
    var times = [];
    var startAll = Date.now();

    function analyzeNext( index ) {
        if ( index >= total ) {
            // All done — show summary
            updateAnalyzeProgress( total, total, 'Terminé !', 0 );

            // Update panel with current slide suggestions
            var curId = sections[ currentSlideIndex ]?.id;
            if ( curId && allResults[ curId ] ) {
                $( '#pl-panel-suggestions' ).html( allResults[ curId ] );
            } else {
                var shown = false;
                for ( var sid in allResults ) {
                    if ( allResults.hasOwnProperty( sid ) ) {
                        $( '#pl-panel-suggestions' ).html( allResults[ sid ] );
                        shown = true;
                        break;
                    }
                }
                if ( ! shown ) {
                    $( '#pl-panel-suggestions' ).html( '<p class="pl-panel-empty">Aucune suggestion générée.</p>' );
                }
            }
            if ( latestScoresHtml ) {
                $( '#pl-sidebar-scores' ).html( latestScoresHtml );
            }

            showAnalyzeSummary( totalSuggestions, total, Date.now() - startAll );
            $btn.prop( 'disabled', false ).html( 'Analyser toutes les diapositives' );
            return;
        }

        var sec = sections[ index ];
        var sectionTitle = sec.title || ( 'Section ' + ( index + 1 ) );
        updateAnalyzeProgress( done, total, '🔍 ' + sectionTitle, times.length ? ( times.reduce( function(a,b){return a+b;}, 0 ) / times.length ) : 0 );

        var t0 = Date.now();

        ajax( 'pl_get_suggestions', { section_id: sec.id, context: 'front' } )
            .done( function( res ) {
                if ( res.success ) {
                    allResults[ sec.id ] = res.data.html || '';
                    suggestionsHtmlCache[ sec.id ] = res.data.html || '';
                    // Count suggestions from the HTML (each .pl-suggestion-card)
                    var tempDiv = document.createElement( 'div' );
                    tempDiv.innerHTML = res.data.html || '';
                    totalSuggestions += tempDiv.querySelectorAll( '.pl-suggestion-card' ).length;

                    if ( res.data.scores_html ) {
                        latestScoresHtml = res.data.scores_html;
                        // Update scores in real-time during analysis
                        $( '#pl-sidebar-scores' ).html( latestScoresHtml );
                    }
                }
            } )
            .fail( function() {
                // Continue even on failure
            } )
            .always( function() {
                var elapsed = Date.now() - t0;
                times.push( elapsed );
                done++;
                var avgTime = times.reduce( function(a,b){return a+b;}, 0 ) / times.length;
                updateAnalyzeProgress( done, total, '✓ ' + sectionTitle, avgTime );

                // Small delay for visual feedback before next
                setTimeout( function() { analyzeNext( index + 1 ); }, 150 );
            } );
    }

    analyzeNext( 0 );
} );

// Close analyze overlay
$( document ).on( 'click', '#pl-analyze-close', function() {
    $( '#pl-analyze-overlay' ).removeClass( 'pl-analyze-overlay--visible' );
    setTimeout( function() { $( '#pl-analyze-overlay' ).remove(); }, 400 );
} );
// Also close on Escape when summary is shown
$( document ).on( 'keydown', function( e ) {
    if ( e.key === 'Escape' && $( '#pl-analyze-close' ).length ) {
        $( '#pl-analyze-close' ).trigger( 'click' );
    }
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
    var proposedContent = $( '#pl-preview-proposed' ).text() || '';
    $btn.prop( 'disabled', true ).text( 'Application…' );

    ajax( 'pl_apply_suggestion', { section_id: sectionId, suggestion_id: suggestionId, proposed_content: proposedContent } )
        .done( function( res ) {
            if ( res.success ) {
                // Update sections array
                for ( var i = 0; i < sections.length; i++ ) {
                    if ( sections[i].id === sectionId ) { sections[i].content = res.data.new_content; break; }
                }
                // Update visible canvas elements
                $( '.pl-canvas-textarea[data-section-id="' + sectionId + '"]' ).val( res.data.new_content );
                var $richtext = $( '.pl-canvas-richtext[data-section-id="' + sectionId + '"]' );
                if ( $richtext.length ) {
                    var lines = ( res.data.new_content || '' ).split( /\n/ );
                    var html = '';
                    for ( var li = 0; li < lines.length; li++ ) {
                        var line = lines[li].trim();
                        if ( line ) html += '<p>' + escHtml( line ) + '</p>';
                    }
                    if ( ! html ) html = '<p><br></p>';
                    $richtext.html( html );
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
// INIT — seed suggestions cache from server-side persisted HTML
// =========================================================================
if ( plWorkbench.cachedSuggestionsHtml ) {
    var cachedHtml = plWorkbench.cachedSuggestionsHtml;
    for ( var secId in cachedHtml ) {
        if ( cachedHtml.hasOwnProperty( secId ) && cachedHtml[ secId ] ) {
            suggestionsHtmlCache[ secId ] = cachedHtml[ secId ];
        }
    }
}

// =========================================================================
// INIT — show first slide with the new visual renderer
// =========================================================================
if ( sections.length > 0 ) {
    // Re-render the first slide through JS to apply the new visual/richtext editor
    showSlide( 0 );
}

// =========================================================================
// AUTO-ANALYZE — trigger when redirected from project creation with ?auto_analyze=1
// =========================================================================
if ( window.location.search.indexOf( 'auto_analyze=1' ) !== -1 && sections.length > 0 ) {
    setTimeout( function() {
        var $btn = $( '#pl-analyze-all' );
        if ( $btn.length ) {
            $btn.trigger( 'click' );
        }
    }, 800 );
}

} )( jQuery );
