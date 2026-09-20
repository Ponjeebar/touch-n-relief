<article class="cr-card cr-labs">
    <div class="cr-card-head">
        <h3>Transaction History</h3>
        <div class="cr-history-tools">
            <div class="cr-history-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="txn-search" type="search" placeholder="Search service, therapist, date..." aria-label="Search transaction history">
            </div>
            <select id="txn-filter" class="cr-history-select" aria-label="Filter transaction history">
                <option value="all">Filter: All</option>
                <option value="cancelled">Cancelled</option>
                <option value="confirm">Confirm</option>
                <option value="completed">Completed</option>
                <option value="upcoming">Upcoming</option>
            </select>
            <select id="txn-sort" class="cr-history-select" aria-label="Sort transaction history">
                <option value="date-desc">Sort: Latest first</option>
                <option value="date-asc">Date: Oldest first</option>
                <option value="amount-desc">Amount: High to low</option>
                <option value="amount-asc">Amount: Low to high</option>
                <option value="service-asc">Service: A to Z</option>
            </select>
        </div>
    </div>
    <div class="cr-txn-list" role="table" aria-label="Treatment history list">
        <div class="cr-txn-head" role="row">
            <div role="columnheader">Txn ID</div>
            <div role="columnheader">Service</div>
            <div role="columnheader">Therapist</div>
            <div role="columnheader">Date</div>
            <div role="columnheader">Status</div>
            <div role="columnheader">Time</div>
            <div role="columnheader">Duration</div>
            <div role="columnheader" class="right">Amount</div>
            <div role="columnheader">Session Notes</div>
        </div>
        @forelse ($transactions as $txn)
            <div
                class="cr-txn-row"
                role="row"
                data-service="{{ strtolower($txn['service']) }}"
                data-therapist="{{ strtolower($txn['therapist']) }}"
                data-date="{{ $txn['date'] }}"
                data-time="{{ $txn['time'] }}"
                data-duration="{{ strtolower($txn['duration']) }}"
                data-amount="{{ preg_replace('/[^0-9.]/', '', $txn['amount']) }}"
                data-status="{{ $txn['status_key'] ?? 'completed' }}"
            >
                <div role="cell" class="txn-id">{{ $txn['transaction_id'] ?? '—' }}</div>
                <div role="cell" class="txn-service">{{ $txn['service'] }}</div>
                <div role="cell" class="txn-therapist">{{ $txn['therapist'] }}</div>
                <div role="cell" class="txn-date">{{ \Illuminate\Support\Carbon::parse($txn['date'])->format('M d, Y') }}</div>
                <div role="cell">
                    <span class="cr-txn-status cr-txn-status-{{ $txn['status_key'] ?? 'completed' }}">
                        {{ $txn['status'] ?? 'Completed' }}
                    </span>
                </div>
                <div role="cell">{{ $txn['time'] }}</div>
                <div role="cell">{{ $txn['duration'] }}</div>
                <div role="cell" class="right amount">{{ $txn['amount'] }}</div>
                <div role="cell" class="txn-note-cell">{{ $txn['notes'] ?: 'No notes.' }}</div>
            </div>
        @empty
            <p class="cr-note-empty" style="padding: 8px 4px 0;">No bookings or treatments found for this client yet.</p>
        @endforelse
    </div>
    <div class="cr-txn-pagination" id="cr-txn-pagination" hidden aria-label="Transaction history pages"></div>
</article>

<script>
    const txnSearch = document.getElementById('txn-search');
    const txnFilter = document.getElementById('txn-filter');
    const txnSort = document.getElementById('txn-sort');
    const txnPagination = document.getElementById('cr-txn-pagination');
    const txnRows = Array.from(document.querySelectorAll('.cr-txn-row[data-service]'));
    const txnPageSize = 4;
    let txnCurrentPage = 1;

    const parseAmount = (row) => {
        const amountRaw = row.getAttribute('data-amount') || '';
        const amount = Number.parseFloat(amountRaw);
        return Number.isFinite(amount) ? amount : 0;
    };

    const parseDateTime = (row) => {
        const date = row.getAttribute('data-date') || '';
        const time = row.getAttribute('data-time') || '';
        const d = new Date(`${date} ${time}`);
        return Number.isFinite(d.getTime()) ? d.getTime() : 0;
    };

    const rowMatchesTools = (row, query, filter) => {
        const hay = [
            row.getAttribute('data-service') || '',
            row.getAttribute('data-therapist') || '',
            row.getAttribute('data-date') || '',
            row.getAttribute('data-time') || '',
            row.getAttribute('data-duration') || '',
            row.getAttribute('data-status') || '',
        ].join(' ');

        const statusKey = row.getAttribute('data-status') || '';
        const matchesQuery = query === '' || hay.includes(query);
        const matchesFilter = filter === 'all'
            || (['cancelled', 'confirm', 'completed', 'upcoming'].includes(filter) && statusKey === filter);

        return matchesQuery && matchesFilter;
    };

    const renderTxnPagination = (totalPages) => {
        if (!txnPagination) return;

        if (totalPages <= 1) {
            txnPagination.hidden = true;
            txnPagination.innerHTML = '';
            return;
        }

        txnPagination.hidden = false;
        txnPagination.innerHTML = '';

        const nav = document.createElement('nav');
        nav.setAttribute('aria-label', 'Transaction history pagination');

        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'cr-txn-page-btn';
        prevBtn.textContent = 'Previous';
        prevBtn.disabled = txnCurrentPage <= 1;
        prevBtn.addEventListener('click', () => {
            if (txnCurrentPage > 1) {
                txnCurrentPage -= 1;
                applyTxnTools(false);
            }
        });
        nav.appendChild(prevBtn);

        for (let page = 1; page <= totalPages; page += 1) {
            const pageBtn = document.createElement('button');
            pageBtn.type = 'button';
            pageBtn.className = 'cr-txn-page-btn' + (page === txnCurrentPage ? ' is-active' : '');
            pageBtn.textContent = String(page);
            pageBtn.setAttribute('aria-label', 'Page ' + page);
            if (page === txnCurrentPage) {
                pageBtn.setAttribute('aria-current', 'page');
            }
            pageBtn.addEventListener('click', () => {
                txnCurrentPage = page;
                applyTxnTools(false);
            });
            nav.appendChild(pageBtn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'cr-txn-page-btn';
        nextBtn.textContent = 'Next';
        nextBtn.disabled = txnCurrentPage >= totalPages;
        nextBtn.addEventListener('click', () => {
            if (txnCurrentPage < totalPages) {
                txnCurrentPage += 1;
                applyTxnTools(false);
            }
        });
        nav.appendChild(nextBtn);

        txnPagination.appendChild(nav);
    };

    const applyTxnTools = (resetPage = true) => {
        const query = (txnSearch?.value || '').trim().toLowerCase();
        const filter = txnFilter?.value || 'all';
        const sort = txnSort?.value || 'date-desc';
        const tbody = document.querySelector('.cr-txn-list');
        if (!tbody) return;

        if (resetPage) {
            txnCurrentPage = 1;
        }

        const sorted = [...txnRows].sort((a, b) => {
            if (sort === 'date-asc') return parseDateTime(a) - parseDateTime(b);
            if (sort === 'amount-desc') return parseAmount(b) - parseAmount(a);
            if (sort === 'amount-asc') return parseAmount(a) - parseAmount(b);
            if (sort === 'service-asc') {
                return (a.getAttribute('data-service') || '').localeCompare(b.getAttribute('data-service') || '');
            }
            return parseDateTime(b) - parseDateTime(a);
        });

        sorted.forEach((row) => tbody.appendChild(row));

        const matchedRows = sorted.filter((row) => rowMatchesTools(row, query, filter));
        const totalPages = Math.max(1, Math.ceil(matchedRows.length / txnPageSize));

        if (txnCurrentPage > totalPages) {
            txnCurrentPage = totalPages;
        }

        txnRows.forEach((row) => {
            row.style.display = 'none';
        });

        matchedRows.forEach((row, index) => {
            const pageStart = (txnCurrentPage - 1) * txnPageSize;
            const pageEnd = pageStart + txnPageSize;
            if (index >= pageStart && index < pageEnd) {
                row.style.display = '';
            }
        });

        renderTxnPagination(totalPages);
    };

    txnSearch?.addEventListener('input', () => applyTxnTools(true));
    txnFilter?.addEventListener('change', () => applyTxnTools(true));
    txnSort?.addEventListener('change', () => applyTxnTools(true));
    applyTxnTools(true);
</script>
