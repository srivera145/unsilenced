/* Small things on the survivor pages: a read-only field marked
 * data-select-on-focus (the new share link) selects its whole value when
 * focused or tapped, so it is easy to copy on a phone.
 */
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('[data-select-on-focus]'), function (field) {
        function selectAll() {
            field.select();
            field.setSelectionRange(0, field.value.length);
        }
        field.addEventListener('focus', selectAll);
        field.addEventListener('click', selectAll);
    });
})();
