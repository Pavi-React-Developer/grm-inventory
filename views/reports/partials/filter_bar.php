<?php
// views/reports/partials/filter_bar.php
// Reusable High-Performance Filter Bar — Auto-Submit without Filter Button

$curFilterType = $filter['filter_type'] ?? 'monthly';
$curYear       = (int)($filter['year'] ?? date('Y'));
$curMonth      = (int)($filter['month'] ?? date('m'));
$curFromDate   = $filter['from_date'] ?? date('Y-m-01');
$curToDate     = $filter['to_date'] ?? date('Y-m-d');
$currentReportType = $reportType ?? 'overview';

$yearsList = [date('Y') + 1, date('Y'), date('Y') - 1, date('Y') - 2];
$monthsList = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="" id="reportFilterForm" class="row g-2 align-items-end">
            <!-- Filter Type Selector -->
            <div class="col-xl-2 col-md-3 col-sm-6">
                <label class="form-label text-muted small fw-semibold mb-1" for="filterTypeSelect">
                    <i class="bi bi-funnel text-primary me-1"></i>Filter Mode
                </label>
                <select name="filter_type" id="filterTypeSelect" class="form-select form-select-sm fw-semibold" onchange="handleFilterTypeChange()">
                    <option value="monthly" <?= $curFilterType === 'monthly' ? 'selected' : '' ?>>Monthly Wise</option>
                    <option value="yearly" <?= $curFilterType === 'yearly' ? 'selected' : '' ?>>Yearly Wise</option>
                    <option value="custom" <?= $curFilterType === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    <option value="all" <?= $curFilterType === 'all' ? 'selected' : '' ?>>All Time</option>
                </select>
            </div>

            <!-- Month Selection (visible for Monthly) -->
            <div class="col-xl-2 col-md-3 col-sm-6 filter-control-group <?= $curFilterType === 'monthly' ? '' : 'd-none' ?>" id="monthInputGroup">
                <label class="form-label text-muted small fw-semibold mb-1">Select Month</label>
                <select name="month" class="form-select form-select-sm" onchange="handleMonthYearChange()">
                    <?php foreach ($monthsList as $mNum => $mName): ?>
                        <option value="<?= $mNum ?>" <?= $curMonth === $mNum ? 'selected' : '' ?>>
                            <?= $mName ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Year Selection (visible for Monthly & Yearly) -->
            <div class="col-xl-2 col-md-2 col-sm-6 filter-control-group <?= in_array($curFilterType, ['monthly', 'yearly']) ? '' : 'd-none' ?>" id="yearInputGroup">
                <label class="form-label text-muted small fw-semibold mb-1">Select Year</label>
                <select name="year" class="form-select form-select-sm" onchange="handleMonthYearChange()">
                    <?php foreach ($yearsList as $yr): ?>
                        <option value="<?= $yr ?>" <?= $curYear === (int)$yr ? 'selected' : '' ?>>
                            <?= $yr ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Custom From Date (Visible for Custom Date Range) -->
            <div class="col-xl-2 col-md-2 col-sm-6 filter-control-group <?= $curFilterType === 'custom' ? '' : 'd-none' ?>" id="fromDateInputGroup">
                <label class="form-label text-muted small fw-semibold mb-1">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= $curFilterType === 'custom' ? e($curFromDate) : '' ?>" onchange="autoSubmitCustomDate()">
            </div>

            <!-- Custom To Date (Visible for Custom Date Range) -->
            <div class="col-xl-2 col-md-2 col-sm-6 filter-control-group <?= $curFilterType === 'custom' ? '' : 'd-none' ?>" id="toDateInputGroup">
                <label class="form-label text-muted small fw-semibold mb-1">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= $curFilterType === 'custom' ? e($curToDate) : '' ?>" onchange="autoSubmitCustomDate()">
            </div>

            <!-- Action Buttons aligned with inputs -->
            <div class="col-xl ms-auto d-flex align-items-center justify-content-sm-end gap-2 mt-2 mt-xl-0 flex-wrap">
                <!-- Download PDF Button (Seamless direct download) -->
                <button type="button" id="btnDownloadPdfFilter" onclick="triggerPdfDownload()" class="btn btn-sm btn-outline-danger fw-semibold shadow-xs" title="Download official PDF report">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i>Download PDF
                </button>

                <!-- Export CSV Button -->
                <a href="<?= url("reports/export/{$currentReportType}?" . http_build_query($_GET)) ?>" 
                   class="btn btn-sm btn-outline-success fw-semibold shadow-xs" title="Export this report to Excel/CSV">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export CSV
                </a>

                <!-- Print Document Button -->
                <a href="<?= url("reports/print/{$currentReportType}?" . http_build_query($_GET)) ?>" 
                   target="_blank" class="btn btn-sm btn-outline-secondary fw-semibold shadow-xs" title="Open printable document view">
                    <i class="bi bi-printer me-1"></i>Print
                </a>
            </div>
        </form>

        <!-- Quick Filter Presets Bar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3 pt-2.5 border-top">
            <div class="d-flex align-items-center flex-wrap gap-1.5">
                <span class="text-muted small me-1 fw-semibold" style="font-size: 0.76rem;">
                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick Presets:
                </span>
                
                <a href="?period=this_month" class="badge rounded-pill text-decoration-none px-2.5 py-1 <?= $curFilterType === 'monthly' && $curMonth === (int)date('m') && $curYear === (int)date('Y') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>">
                    This Month
                </a>
                <a href="?period=last_month" class="badge rounded-pill text-decoration-none px-2.5 py-1 <?= $curFilterType === 'last_month' ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>">
                    Last Month
                </a>
                <a href="?period=this_quarter" class="badge rounded-pill text-decoration-none px-2.5 py-1 <?= $curFilterType === 'this_quarter' ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>">
                    This Quarter
                </a>
                <a href="?period=yearly&year=<?= date('Y') ?>" class="badge rounded-pill text-decoration-none px-2.5 py-1 <?= $curFilterType === 'yearly' && $curYear === (int)date('Y') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>">
                    Year <?= date('Y') ?>
                </a>
                <a href="?period=all" class="badge rounded-pill text-decoration-none px-2.5 py-1 <?= $curFilterType === 'all' ? 'bg-primary text-white' : 'bg-light text-secondary border' ?>">
                    All Time
                </a>
            </div>

            <!-- Active Date Tag Badge -->
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 font-monospace" style="font-size: 0.78rem;">
                    <i class="bi bi-calendar-check me-1"></i><?= e($filter['label']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
function handleFilterTypeChange() {
    const mode = document.getElementById('filterTypeSelect').value;
    const mGroup = document.getElementById('monthInputGroup');
    const yGroup = document.getElementById('yearInputGroup');
    const fGroup = document.getElementById('fromDateInputGroup');
    const tGroup = document.getElementById('toDateInputGroup');

    // Dynamically toggle inputs based on mode
    if (mode === 'custom') {
        mGroup.classList.add('d-none');
        yGroup.classList.add('d-none');
        fGroup.classList.remove('d-none');
        tGroup.classList.remove('d-none');
        // Do not auto submit right away for 'custom', wait for them to pick dates
        return;
    } else {
        // Clear custom dates if switching away from custom
        document.querySelector('[name="from_date"]').value = '';
        document.querySelector('[name="to_date"]').value = '';
        
        if (mode === 'monthly') {
            mGroup.classList.remove('d-none');
            yGroup.classList.remove('d-none');
            fGroup.classList.add('d-none');
            tGroup.classList.add('d-none');
        } else if (mode === 'yearly') {
            mGroup.classList.add('d-none');
            yGroup.classList.remove('d-none');
            fGroup.classList.add('d-none');
            tGroup.classList.add('d-none');
        } else if (mode === 'all') {
            mGroup.classList.add('d-none');
            yGroup.classList.add('d-none');
            fGroup.classList.add('d-none');
            tGroup.classList.add('d-none');
        }
        document.getElementById('reportFilterForm').submit();
    }
}

function handleMonthYearChange() {
    document.querySelector('[name="from_date"]').value = '';
    document.querySelector('[name="to_date"]').value = '';
    document.getElementById('reportFilterForm').submit();
}

function autoSubmitCustomDate() {
    const fromDate = document.querySelector('[name="from_date"]').value;
    const toDate = document.querySelector('[name="to_date"]').value;
    
    // Only auto-submit when both dates are filled
    if (fromDate && toDate) {
        document.getElementById('filterTypeSelect').value = 'custom';
        document.getElementById('reportFilterForm').submit();
    }
}

let currentPdfBtn = null;
let currentBtnHtml = '';

// Preload the iframe silently so it's ready when the user clicks
document.addEventListener('DOMContentLoaded', function() {
    let iframe = document.getElementById('pdfHiddenIframe');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'pdfHiddenIframe';
        iframe.style.position = 'absolute';
        iframe.style.left = '-9999px';
        iframe.style.top = '-9999px';
        iframe.style.width = '1200px';
        iframe.style.height = '1200px';
        iframe.src = '<?= url("reports/print/{$currentReportType}?" . http_build_query(array_merge($_GET, ['pdf' => '0']))) ?>';
        document.body.appendChild(iframe);
    }
    
    // Listen for completion
    window.addEventListener('message', function(e) {
        if (e.data === 'PDF_GENERATED' || e.data === 'PDF_FAILED') {
            const btn = currentPdfBtn || document.getElementById('btnDownloadPdfFilter');
            if (btn) {
                btn.innerHTML = currentPdfBtn ? currentBtnHtml : '<i class="bi bi-file-earmark-pdf-fill me-1"></i>Download PDF';
                btn.disabled = false;
                btn.classList.remove('disabled');
            }
            if(e.data === 'PDF_FAILED') {
                alert('Failed to generate PDF. Please try the Print Document option.');
            }
        }
    });
});

function triggerPdfDownload(url = null, btnElement = null) {
    currentPdfBtn = btnElement || document.getElementById('btnDownloadPdfFilter');
    if (!currentPdfBtn) return;
    
    currentBtnHtml = currentPdfBtn.innerHTML;
    currentPdfBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    currentPdfBtn.disabled = true;
    currentPdfBtn.classList.add('disabled');
    
    const iframe = document.getElementById('pdfHiddenIframe');
    if (url && iframe) {
        // Load the new url. The url contains pdf=1 so it will auto-trigger inside the iframe.
        iframe.src = url;
    } else if (iframe && iframe.contentWindow) {
        // Trigger the already preloaded main report
        iframe.contentWindow.postMessage('START_PDF', '*');
    }
    
    // Failsafe timeout
    setTimeout(() => {
        if (currentPdfBtn && currentPdfBtn.disabled) {
            currentPdfBtn.innerHTML = currentBtnHtml;
            currentPdfBtn.disabled = false;
            currentPdfBtn.classList.remove('disabled');
        }
    }, 10000);
}
</script>
