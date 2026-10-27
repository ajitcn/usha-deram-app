/* USHA DERMA shared modal layer.
 * Keeps Bootstrap modal behavior intact while preventing ancestor stacking
 * contexts and stale backdrops from making modal controls unclickable.
 */
(function (window, document, $) {
    'use strict';

    var placeholderKey = 'ushaDermaModalPlaceholder';
    var cleanupTimer = null;

    function portal(modal) {
        if (!modal || modal.parentNode === document.body || $.data(modal, placeholderKey)) return;
        var placeholder = document.createComment('usha-derma-modal-placeholder');
        modal.parentNode.insertBefore(placeholder, modal);
        document.body.appendChild(modal);
        $.data(modal, placeholderKey, placeholder);
    }

    function restore(modal) {
        var placeholder = $.data(modal, placeholderKey);
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.insertBefore(modal, placeholder.nextSibling);
            placeholder.parentNode.removeChild(placeholder);
        }
        $.removeData(modal, placeholderKey);
    }

    function cleanupBackdrops() {
        var active = $('.modal.show').length;
        var backdrops = $('.modal-backdrop');
        if (!active) {
            backdrops.remove();
            $('body').removeClass('modal-open');
            return;
        }
        if (backdrops.length > active) {
            backdrops.slice(0, backdrops.length - active).remove();
        }
    }

    function scheduleCleanup() {
        window.clearTimeout(cleanupTimer);
        cleanupTimer = window.setTimeout(cleanupBackdrops, 80);
    }

    $(document)
        .off('show.bs.modal.ushaDermaLayer', '.modal')
        .on('show.bs.modal.ushaDermaLayer', '.modal', function () {
            portal(this);
        })
        .off('shown.bs.modal.ushaDermaLayer', '.modal')
        .on('shown.bs.modal.ushaDermaLayer', '.modal', function () {
            cleanupBackdrops();
        })
        .off('hidden.bs.modal.ushaDermaLayer', '.modal')
        .on('hidden.bs.modal.ushaDermaLayer', '.modal', function () {
            restore(this);
            scheduleCleanup();
        })
        .off('ajaxError.ushaDermaLayer ajaxStop.ushaDermaLayer')
        .on('ajaxError.ushaDermaLayer ajaxStop.ushaDermaLayer', scheduleCleanup);
}(window, document, window.jQuery));
