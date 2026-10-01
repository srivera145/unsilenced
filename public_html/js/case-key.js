/* The case-key page (views/survivor/submit-key.php).
 *
 * She types the last two words back. When they match, the key is removed
 * from the page (it was only ever in this one response) and the next steps
 * are shown. Comparison is case-insensitive and ignores spaces, like the
 * server's.
 */
(function () {
    var panel = document.getElementById('key-panel');
    var check = document.getElementById('key-check');
    var done = document.getElementById('key-done');
    if (!panel || !check || !done) {
        return;
    }

    var words = Array.prototype.map.call(document.querySelectorAll('#case-key li'), function (item) {
        return item.textContent.trim().toLowerCase();
    });
    var error = document.getElementById('key-check-error');
    var fifth = document.getElementById('key-word-5');
    var sixth = document.getElementById('key-word-6');

    check.hidden = false;
    done.hidden = true;

    function normalize(value) {
        return value.toLowerCase().replace(/[^a-z]/g, '');
    }

    function confirm() {
        if (normalize(fifth.value) === words[4] && normalize(sixth.value) === words[5]) {
            panel.remove();
            done.hidden = false;
            document.getElementById('done-title').focus();
            return;
        }
        error.textContent = 'Those words do not match the last two words of your key. Look at your key again and check each word.';
        error.hidden = false;
        fifth.focus();
    }

    document.getElementById('key-check-button').addEventListener('click', confirm);
    [fifth, sixth].forEach(function (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirm();
            }
        });
    });
})();
