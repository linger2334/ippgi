/**
 * Initialize sortable functionality for language ordering
 */
jQuery( function() {
    // Enable drag-and-drop sorting for languages
    jQuery( '#trp-sortable-languages' ).sortable({
        handle: '.trp-sortable-handle'
    });
});
