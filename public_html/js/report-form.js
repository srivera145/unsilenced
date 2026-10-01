/* The report form at /submit and the edit form at /my-report/edit
 * (views/survivor/submit.php, my-report-edit.php).
 *
 * On /submit: one step at a time, checked before moving on; the answers stay
 * in this page until Send. Nothing is sent before then except, when leaving
 * step 6, her account to /submit/scan, which looks for names and contact
 * details and keeps nothing. Send first solves the proof of work (pow.js).
 *
 * On both: the school search, the reporting follow-up questions, the
 * character count and the evidence file list.
 *
 * No beforeunload warning, deliberately: it would stop the quick exit.
 */
(function () {
    var form = document.getElementById('report-form');
    if (!form) {
        return;
    }

    var byId = function (id) { return document.getElementById(id); };
    var all = function (selector, root) { return Array.prototype.slice.call((root || form).querySelectorAll(selector)); };
    var csrfInput = form.querySelector('input[name="_csrf"]');
    var csrf = csrfInput ? csrfInput.value : '';
    var stepped = form.hasAttribute('data-step-count');

    // --- small helpers ---------------------------------------------------------

    function checked(name) {
        return all('input[name="' + name + '"]:checked');
    }

    // A choice's own words: the bold label of a card choice (not its note), or a plain label's text.
    function labelOf(input) {
        var label = input.closest('label');
        var text = label ? (label.querySelector('.fw-semi') || label.querySelector('span')) : null;
        return (text || label || input).textContent.trim();
    }

    function setError(step, message, focusTarget) {
        var box = step.querySelector('.step-error');
        if (!box) {
            box = document.createElement('p');
            box.className = 'error step-error';
            box.setAttribute('role', 'alert');
            step.querySelector('.step-nav').before(box);
        }
        box.textContent = message;
        box.hidden = !message;
        if (message && focusTarget) {
            focusTarget.focus();
        }
        return !message;
    }

    // --- school search -----------------------------------------------------------

    var schoolInput = byId('school_id');
    var search = byId('school-search');
    var searchButton = byId('school-search-button');
    var results = byId('school-results');
    var status = byId('school-status');
    var chosen = byId('school-chosen');
    var change = byId('school-change');
    var searchTimer = null;
    var lastQuery = '';

    function chooseSchool(id, name, place) {
        schoolInput.value = id;
        byId('school-chosen-name').textContent = name;
        byId('school-chosen-place').textContent = place;
        chosen.hidden = false;
        byId('school-search-field').hidden = true;
        results.textContent = '';
        status.textContent = name + ' chosen.';
        change.focus();
    }

    function renderSchools(list) {
        results.textContent = '';
        if (!list.length) {
            status.textContent = 'No schools found. Try fewer letters, or the city.';
            return;
        }
        var fieldset = document.createElement('fieldset');
        fieldset.className = 'fieldset';
        var legend = document.createElement('legend');
        legend.textContent = 'Choose your school';
        fieldset.appendChild(legend);
        var stack = document.createElement('div');
        stack.className = 'stack stack-1';
        list.forEach(function (school) {
            var label = document.createElement('label');
            label.className = 'check';
            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'school_choice';
            radio.value = school.id;
            var text = document.createElement('span');
            text.className = 'check-text';
            var name = document.createElement('span');
            name.textContent = school.name;
            var place = document.createElement('span');
            place.className = 'check-note';
            place.textContent = school.place;
            text.appendChild(name);
            text.appendChild(place);
            label.appendChild(radio);
            label.appendChild(text);
            radio.addEventListener('change', function () {
                chooseSchool(String(school.id), school.name, school.place);
            });
            stack.appendChild(label);
        });
        fieldset.appendChild(stack);
        results.appendChild(fieldset);
        status.textContent = list.length === 1 ? '1 school found.' : list.length + ' schools found.';
    }

    function runSearch() {
        var query = search.value.trim();
        if (query.length < 2 || query === lastQuery) {
            return;
        }
        lastQuery = query;
        status.textContent = 'Searching…';
        // Each area keeps to its own path, so its own cookie: /submit/schools or /my-report/schools.
        var url = form.getAttribute('data-schools-url') || '/submit/schools';
        fetch(url + '?q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { renderSchools(data.schools || []); })
            .catch(function () { status.textContent = 'The search did not work. Check your connection and try again.'; lastQuery = ''; });
    }

    if (search && schoolInput) {
        searchButton.hidden = false;
        change.hidden = false;
        search.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runSearch, 350);
        });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                lastQuery = '';
                runSearch();
            }
        });
        searchButton.addEventListener('click', function () { lastQuery = ''; runSearch(); });
        change.addEventListener('click', function () {
            chosen.hidden = true;
            byId('school-search-field').hidden = false;
            lastQuery = '';
            search.focus();
        });
    }

    // --- reporting follow-ups ------------------------------------------------------

    function syncReported() {
        var answer = checked('reported_to_school')[0];
        all('[data-if-reported]').forEach(function (group) {
            group.hidden = !answer || group.getAttribute('data-if-reported') !== answer.value;
        });
    }
    all('input[name="reported_to_school"]').forEach(function (radio) { radio.addEventListener('change', syncReported); });
    syncReported();

    // --- account: count and the name check ----------------------------------------------

    var account = byId('account');
    var count = byId('account-count');
    var scanResults = byId('scan-results');
    var scanPreview = byId('scan-preview');
    var scanConfirm = byId('name_scan_confirmed');
    var lastScanned = scanResults && !scanResults.hidden && account ? account.value.trim() : null;

    function updateCount() {
        var max = parseInt(count.getAttribute('data-max'), 10);
        var used = account.value.length;
        count.textContent = used.toLocaleString() + ' of ' + max.toLocaleString() + ' characters';
    }
    if (account && count) {
        account.addEventListener('input', updateCount);
        updateCount();
    }

    function renderScan(segments) {
        scanPreview.textContent = '';
        segments.forEach(function (segment) {
            if (!segment.type) {
                scanPreview.appendChild(document.createTextNode(segment.text));
                return;
            }
            var mark = document.createElement('mark');
            mark.className = 'scan-mark';
            mark.textContent = segment.text;
            var hidden = document.createElement('span');
            hidden.className = 'sr-only';
            hidden.textContent = ' (' + segment.label + ')';
            mark.appendChild(hidden);
            scanPreview.appendChild(mark);
        });
    }

    // Resolves true when the account may go forward as it is, false when the
    // highlights are showing and she has neither changed nor confirmed, and
    // 'fresh' when the highlights have only just appeared.
    function checkAccount() {
        var text = account ? account.value.trim() : '';
        if (!text) {
            if (scanResults) { scanResults.hidden = true; }
            return Promise.resolve(true);
        }
        if (text === lastScanned) {
            return Promise.resolve(scanResults.hidden || (scanConfirm && scanConfirm.checked));
        }
        return fetch('/submit/scan', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ account: account.value, school_id: schoolInput ? schoolInput.value : '' })
        }).then(function (response) {
            if (!response.ok) { throw new Error('scan'); }
            return response.json();
        }).then(function (data) {
            lastScanned = text;
            if (!data.count) {
                scanResults.hidden = true;
                return true;
            }
            renderScan(data.segments || []);
            scanConfirm.checked = false;
            scanResults.hidden = false;
            byId('scan-title').focus();
            return 'fresh';
        }).catch(function () {
            // The server checks again on Send; don't trap her here.
            return true;
        });
    }

    if (account) {
        account.addEventListener('input', function () {
            if (scanConfirm && account.value.trim() !== lastScanned) {
                scanConfirm.checked = false;
            }
        });
    }

    // --- evidence -------------------------------------------------------------------

    var fileInput = byId('evidence');
    var fileAttest = byId('evidence_attest');
    var fileList = byId('evidence-list');
    var maxFiles = parseInt(form.getAttribute('data-max-files') || '20', 10);
    var maxBytes = parseInt(form.getAttribute('data-max-file-bytes') || '20971520', 10);

    function sizeLabel(bytes) {
        return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function syncFiles() {
        if (!fileInput) { return; }
        var allowed = !fileAttest || fileAttest.checked;
        fileInput.disabled = !allowed;
        fileInput.closest('.file').classList.toggle('is-disabled', !allowed);
        if (!allowed && fileInput.value) {
            fileInput.value = '';
        }
        fileList.textContent = '';
        Array.prototype.forEach.call(fileInput.files || [], function (file) {
            var item = document.createElement('li');
            item.textContent = file.name + ' · ' + sizeLabel(file.size) + (file.size > maxBytes ? ' (too large: the limit is ' + sizeLabel(maxBytes) + ')' : '');
            fileList.appendChild(item);
        });
    }
    if (fileInput) {
        fileInput.addEventListener('change', syncFiles);
        if (fileAttest) { fileAttest.addEventListener('change', syncFiles); }
        syncFiles();
    }

    // --- the steps (/submit only) --------------------------------------------------------

    if (!stepped) {
        return;
    }

    var steps = all('.form-step');
    var total = steps.length;
    var current = 1;
    var progress = byId('step-progress');
    var progressText = byId('step-progress-text');
    var postMax = parseInt(form.getAttribute('data-post-max') || '0', 10);

    var checks = {
        2: function (step) {
            return setError(step, schoolInput.value ? '' : 'Search for your school, then choose it from the list.', search);
        },
        3: function (step) {
            return setError(step, byId('incident_year').value ? '' : 'Choose the year it happened. If you are not sure, choose your best guess.', byId('incident_year'));
        },
        4: function (step) {
            return setError(step, checked('perpetrator').length ? '' : 'Choose one. "I would rather not say" is fine.', step.querySelector('input[name="perpetrator"]'));
        },
        5: function (step) {
            var answer = checked('reported_to_school')[0];
            if (!answer) {
                return setError(step, 'Choose yes or no.', step.querySelector('input[name="reported_to_school"]'));
            }
            if (answer.value === 'yes' && !checked('school_channels[]').length) {
                return setError(step, 'Choose who you reported to at the school.', step.querySelector('input[name="school_channels[]"]'));
            }
            return setError(step, '');
        },
        6: function (step) {
            setError(step, '');
            return checkAccount().then(function (result) {
                if (result === 'fresh') {
                    return false; // the highlights just appeared, and have focus: no error yet
                }
                if (!result && scanConfirm && !scanConfirm.checked) {
                    return setError(step, 'Change your account to take these out, or tick the box to confirm you have checked them.', scanConfirm);
                }
                return true;
            });
        },
        7: function (step) {
            var choice = checked('consent')[0];
            if (!choice) {
                return setError(step, 'Choose what we may do with your report.', step.querySelector('input[name="consent"]'));
            }
            if (choice.value === 'stats_and_account' && !(account && account.value.trim())) {
                return setError(step, 'To publish your account, write it in step 6, or choose another option.', choice);
            }
            return setError(step, '');
        },
        8: function (step) {
            var files = fileInput && fileInput.files ? Array.prototype.slice.call(fileInput.files) : [];
            if (!files.length) {
                return setError(step, '');
            }
            if (fileAttest && !fileAttest.checked) {
                return setError(step, 'Please confirm you are not adding intimate images, or remove the files.', fileAttest);
            }
            if (files.length > maxFiles) {
                return setError(step, 'A report can have up to ' + maxFiles + ' files. Choose fewer now and add the rest later from your page.', fileInput);
            }
            var tooBig = files.filter(function (file) { return file.size > maxBytes; });
            if (tooBig.length) {
                return setError(step, tooBig.map(function (file) { return file.name; }).join(', ') + ': larger than ' + sizeLabel(maxBytes) + ', so it cannot be added.', fileInput);
            }
            var sum = files.reduce(function (total, file) { return total + file.size; }, 0);
            if (postMax && sum > postMax * 0.95) {
                return setError(step, 'These files are too large to send together (' + sizeLabel(sum) + '). Choose fewer now, and add the rest from your page after you send.', fileInput);
            }
            return setError(step, '');
        },
        9: function (step) {
            return setError(step, byId('attest').checked ? '' : 'Please confirm this is true to the best of your knowledge.', byId('attest'));
        }
    };

    function run(number) {
        var check = checks[number];
        return Promise.resolve(check ? check(steps[number - 1]) : true);
    }

    function show(number, focus) {
        current = number;
        steps.forEach(function (step) {
            step.hidden = parseInt(step.getAttribute('data-step'), 10) !== number;
        });
        progress.value = number;
        progressText.textContent = 'Step ' + number + ' of ' + total + ': ' + steps[number - 1].querySelector('h2').textContent;
        if (number === total) {
            summarize();
        }
        if (focus) {
            var heading = steps[number - 1].querySelector('h2');
            heading.focus();
            window.scrollTo(0, 0);
        }
    }

    // Step 9's review: every answer, read back from the form, with a way back to it.
    function summarize() {
        var list = byId('review-summary');
        list.textContent = '';
        function row(title, value, step) {
            var dt = document.createElement('dt');
            dt.textContent = title;
            var dd = document.createElement('dd');
            dd.className = 'review-row';
            var text = document.createElement('span');
            text.textContent = value;
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-ghost btn-sm';
            button.textContent = 'Change';
            button.setAttribute('aria-label', 'Change ' + title.toLowerCase());
            button.addEventListener('click', function () { show(step, true); });
            dd.appendChild(text);
            dd.appendChild(button);
            list.appendChild(dt);
            list.appendChild(dd);
        }
        var names = function (name) { return checked(name).map(labelOf).join('; '); };
        var year = byId('incident_year');
        var reported = checked('reported_to_school')[0];
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;

        row('School', schoolInput.value ? byId('school-chosen-name').textContent : 'Not chosen', 2);
        row('Year', year.value || 'Not chosen', 3);
        row('Time of year', checked('incident_season').filter(function (r) { return r.value; }).map(labelOf).join('') || 'Not given', 3);
        row('Where', checked('setting').filter(function (r) { return r.value; }).map(labelOf).join('') || 'Not given', 3);
        row('Who', names('perpetrator') || 'Not chosen', 4);
        row('Reported to the school', reported ? (reported.value === 'yes' ? 'Yes' + (names('school_channels[]') ? ': ' + names('school_channels[]') : '') : 'No') : 'Not answered', 5);
        if (reported && reported.value === 'yes') {
            row('What happened next', names('school_outcomes[]') || 'Not given', 5);
            row('How the school responded', checked('response_rating').filter(function (r) { return r.value; }).map(labelOf).join('') || 'Not rated', 5);
        } else if (reported) {
            row('Why not', names('not_reported_reasons[]') || 'Not given', 5);
        }
        row('Reported to the police', names('reported_to_police') || 'Not given', 5);
        row('Your account', account && account.value.trim() ? account.value.trim().length.toLocaleString() + ' characters' : 'Not written', 6);
        row('Publishing', names('consent') || 'Not chosen', 7);
        row('Evidence', files ? files + (files === 1 ? ' file' : ' files') : 'None', 8);
    }

    form.classList.add('is-stepped');
    form.querySelector('.step-progress').hidden = false;
    all('[data-step-next], [data-step-back]').forEach(function (button) { button.hidden = false; });

    all('[data-step-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.disabled = true;
            run(current).then(function (ok) {
                button.disabled = false;
                if (ok) {
                    show(current + 1, true);
                }
            });
        });
    });
    all('[data-step-back]').forEach(function (button) {
        button.addEventListener('click', function () { show(current - 1, true); });
    });

    // Enter in a text box must not send the whole form from step 2.
    form.addEventListener('keydown', function (event) {
        var target = event.target;
        if (event.key === 'Enter' && target.tagName === 'INPUT' && target.type !== 'checkbox' && target.type !== 'radio' && current < total) {
            event.preventDefault();
        }
    });

    var sending = false;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (sending) {
            return;
        }

        // Every step again, in order: the first that fails is shown.
        var number = 2;
        function next() {
            if (number > total) {
                return send();
            }
            var checking = number;
            return run(checking).then(function (ok) {
                if (!ok) {
                    show(checking, false);
                    return;
                }
                number++;
                return next();
            });
        }
        next();
    });

    function send() {
        var sendStatus = byId('send-status');
        var button = byId('send-report');
        sending = true;
        button.disabled = true;
        sendStatus.textContent = 'Checking that this came from a person. This takes a few seconds…';

        var challenge = form.querySelector('input[name="pow_challenge"]').value;
        var bits = parseInt(form.getAttribute('data-pow-bits'), 10);

        return window.UnsilencedPow.solve(challenge, bits).then(function (nonce) {
            form.querySelector('input[name="pow_nonce"]').value = nonce;
            sendStatus.textContent = 'Sending your report…';
            HTMLFormElement.prototype.submit.call(form);
        });
    }

    var start = parseInt(form.getAttribute('data-start-step'), 10) || 1;
    show(Math.min(Math.max(start, 1), total), start > 1);
    if (start > 1) {
        var firstError = steps[start - 1].querySelector('.error, [aria-invalid="true"]');
        if (firstError && firstError.id) {
            steps[start - 1].querySelector('h2').focus();
        }
    }
})();
