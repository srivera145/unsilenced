/* The admin review page (views/admin/reports/show.php): the placeholder
 * buttons put "[name removed]" and the like into the published text where the
 * cursor is, replacing any selected words, which is how a redaction is made.
 */
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-placeholders-for]'), function (group) {
        var textarea = document.getElementById(group.getAttribute('data-placeholders-for'));
        if (!textarea) {
            return;
        }

        group.addEventListener('click', function (event) {
            var button = event.target.closest('[data-placeholder]');
            if (!button) {
                return;
            }
            var text = button.getAttribute('data-placeholder');
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.setRangeText(text, start, end, 'end');
            textarea.focus();
        });
    });
})();
