<!-- PAGE HEADER -->
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-diagram-3-fill me-2 text-primary"></i>Product Catalog</h2>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary shadow-sm fw-semibold" onclick="openAddCategoryModal()">
            <i class="bi bi-plus-circle me-1"></i> Add Category
        </button>
    </div>
</div>

<!-- KPI SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-primary border-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Active Categories</span>
                    <h4 class="mb-0 fw-bold mt-1 text-dark" id="kpiCatCount"><?= number_format($catalogStats['total_categories'] ?? count($categories ?? [])) ?></h4>
                    <small class="text-muted">Primary department groups</small>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3"><i class="bi bi-folder2-open fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-success border-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Total Subcategories</span>
                    <h4 class="mb-0 fw-bold mt-1 text-success" id="kpiSubCount"><?= number_format($catalogStats['total_subcategories'] ?? 0) ?></h4>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3"><i class="bi bi-diagram-3 fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-info border-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Sub-Subcategories</span>
                    <h4 class="mb-0 fw-bold mt-1 text-info" id="kpiSubSubCount"><?= number_format($catalogStats['total_sub_subcategories'] ?? 0) ?></h4>
                    <small class="text-muted">Specific style classifications</small>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3"><i class="bi bi-tags fs-4"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-warning border-4 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold">Pre-set Attributes</span>
                    <h4 class="mb-0 fw-bold mt-1 text-warning" id="kpiAttrCount"><?= number_format($catalogStats['total_attributes'] ?? 0) ?></h4>
                    <small class="text-muted">Category-scoped specifications</small>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-3"><i class="bi bi-sliders fs-4"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- SEARCH & FILTER TOOLBAR (Image 1 Style with View Toggle) -->
<div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
    <div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-5 col-12">
                <label class="form-label small fw-semibold text-muted mb-1">Search Category / Subcategory / Style / Attributes</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="globalCatalogSearch" class="form-control" placeholder="Search by name, code, attribute..." oninput="handleGlobalSearch(this.value)">
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small fw-semibold text-muted mb-1">Category Type</label>
                <select class="form-select form-select-sm" id="catalogTypeFilter" onchange="applyTypeFilter(this.value)">
                    <option value="all" selected>All Types</option>
                    <option value="NORMAL">Normal Category</option>
                    <option value="COMPLEMENTARY">Complementary Category</option>
                    <option value="SERIES">Series Category</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-2 col-6">
                <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                <select class="form-select form-select-sm" id="catalogStatusFilter" onchange="applyStatusFilter(this.value)">
                    <option value="active" selected>Active Only</option>
                    <option value="all">All Status</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-2 col-6 d-flex align-items-end gap-1">
                <button type="button" class="btn btn-sm btn-primary px-3 fw-semibold flex-grow-1" onclick="applyFilters()" style="background:#5551ff;border-color:#5551ff;">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary px-2" onclick="resetFilters()" title="Reset Filters">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="col-lg-2 col-12 d-flex justify-content-lg-end justify-content-start align-items-end mt-2 mt-lg-0">
                <!-- VIEW MODE SWITCHER: Hierarchy List vs Cascading Columns -->
                <div class="btn-group btn-group-sm p-1 bg-light rounded-pill border" role="group" aria-label="Catalog View Mode">
                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold active btn-primary shadow-sm" id="btnToggleList" onclick="switchCatalogView('list')">
                        <i class="bi bi-list-ul me-1"></i> List
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill px-3 fw-semibold btn-light text-muted" id="btnToggleColumns" onclick="switchCatalogView('columns')">
                        <i class="bi bi-columns-gap me-1"></i> Columns
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CATEGORY HIERARCHY FLOW BREADCRUMB STRIP -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 px-1">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-primary px-3 py-1 fw-bold" style="font-size:0.82rem;">
            <i class="bi bi-diagram-3-fill me-1"></i>Category Hierarchy Flow:
        </span>
        <div class="d-flex align-items-center gap-1 small fw-semibold text-muted flex-wrap">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">1. Category</span>
            <i class="bi bi-chevron-right text-muted"></i>
            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">2. Sub Category</span>
            <i class="bi bi-chevron-right text-muted"></i>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">3. Sub-Sub Category</span>
            <i class="bi bi-chevron-right text-muted"></i>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1"><i class="bi bi-sliders me-1"></i>Pre-set Attributes</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="reloadCategoryTree()">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
        </button>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VIEW 1: CATEGORY HIERARCHY FLOW IN LIST VIEW (IMAGE 1 UI STYLE) -->
<!-- ========================================================================= -->
<div id="catalogListViewContainer">
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tableCatalogHierarchyList">
                <thead class="table-light" style="border-bottom:2px solid #eef2f6;">
                    <tr class="text-uppercase" style="font-size:0.75rem; letter-spacing:0.5px; color:#64748b;">
                        <th style="width:24%; padding:14px 18px;">Category / Department</th>
                        <th style="width:24%; padding:14px 14px;">Sub Categories</th>
                        <th style="width:20%; padding:14px 14px;">Sub-Sub Categories</th>
                        <th style="width:18%; padding:14px 14px;">Pre-set Attributes (Scoped)</th>
                        <th class="text-center" style="width:6%; padding:14px 12px;">Status</th>
                        <th class="text-end" style="width:8%; padding:14px 18px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="catalogListTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                            <div class="small fw-semibold">Loading Category Hierarchy Flow...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- VIEW 2: CASCADING COLUMNS VIEW (IMAGE 2 UI STYLE) -->
<!-- ========================================================================= -->
<div id="catalogColumnsViewContainer" style="display:none;">
    <div class="row g-3" id="catalogHierarchyRow">

        <!-- COL 1: CATEGORY -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column" style="border:1px solid #e8ecf0!important;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="cat-level-num bg-primary">1</span>
                        <h6 class="card-title mb-0 fw-bold text-dark">Category</h6>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="badgeCatCount">0</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 rounded-2 fw-semibold" onclick="openAddCategoryModal()">
                        <i class="bi bi-plus-lg me-1"></i>Add
                    </button>
                </div>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted" style="font-size:.75rem;"></i></span>
                        <input type="text" id="filterCatInput" class="form-control form-control-sm border-start-0" placeholder="Search category..." oninput="filterColList('categoriesListContainer', this.value)">
                    </div>
                </div>
                <div class="flex-grow-1 overflow-auto p-0" id="categoriesListContainer" style="max-height:520px;min-height:320px;">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2"></div>
                        <div class="small">Loading categories...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COL 2: SUB CATEGORY -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column" style="border:1px solid #e8ecf0!important;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <span class="cat-level-num bg-info">2</span>
                        <h6 class="card-title mb-0 fw-bold text-dark text-truncate" id="col2HeaderTitle">Sub Category</h6>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="badgeSubCount">0</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-info py-1 px-2 rounded-2 fw-semibold" id="btnAddSubcatBtn" onclick="openAddSubcatForSelected()" disabled>
                        <i class="bi bi-plus-lg me-1"></i>Add
                    </button>
                </div>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted" style="font-size:.75rem;"></i></span>
                        <input type="text" id="filterSubInput" class="form-control form-control-sm border-start-0" placeholder="Search subcategory..." oninput="filterColList('subcategoriesListContainer', this.value)">
                    </div>
                </div>
                <div class="flex-grow-1 overflow-auto p-0" id="subcategoriesListContainer" style="max-height:520px;min-height:320px;">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-arrow-left-circle fs-3 text-secondary opacity-50 d-block mb-2"></i>
                        <div class="small fw-semibold">Select a Category first</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COL 3: SUB-SUB CATEGORY -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column" style="border:1px solid #e8ecf0!important;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <span class="cat-level-num bg-success">3</span>
                        <h6 class="card-title mb-0 fw-bold text-dark text-truncate" id="col3HeaderTitle">Sub-Sub Category</h6>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="badgeSubSubCount">0</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 rounded-2 fw-semibold" id="btnAddSubSubcatBtn" onclick="openAddSubSubcatForSelected()" disabled>
                        <i class="bi bi-plus-lg me-1"></i>Add
                    </button>
                </div>
                <div class="px-2 pt-2 pb-1 border-bottom">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted" style="font-size:.75rem;"></i></span>
                        <input type="text" id="filterSubSubInput" class="form-control form-control-sm border-start-0" placeholder="Search sub-subcategory..." oninput="filterColList('subSubcategoriesListContainer', this.value)">
                    </div>
                </div>
                <div class="flex-grow-1 overflow-auto p-0" id="subSubcategoriesListContainer" style="max-height:520px;min-height:320px;">
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-arrow-left-circle fs-3 text-secondary opacity-50 d-block mb-2"></i>
                        <div class="small fw-semibold">Select a Sub Category first</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COL 4: PRE-SET ATTRIBUTES (INLINE, CATEGORY-DEPENDENT) -->
        <div class="col-xl-3 col-lg-6 col-md-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column" style="border:1px solid #e8ecf0!important;">
                <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <span class="cat-level-num bg-warning text-dark">4</span>
                        <h6 class="card-title mb-0 fw-bold text-dark text-truncate" id="col4HeaderTitle">
                            <i class="bi bi-sliders me-1 text-warning"></i>Attributes
                        </h6>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill" id="attrCountBadge">—</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-warning py-1 px-2 rounded-2 fw-semibold" id="btnAddAttrRow" onclick="addAttributeRow()" disabled>
                        <i class="bi bi-plus-lg me-1"></i>Add
                    </button>
                </div>
                <!-- Scope + Save row -->
                <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <span class="small text-truncate" style="max-width:180px;">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        <span id="labelActiveBranchScope" class="fw-semibold text-primary" style="font-size:.78rem;">Select category to configure</span>
                    </span>
                    <button type="button" class="btn btn-success fw-semibold" id="btnSaveAttrs" onclick="saveCategoryAttributes()" disabled style="font-size:.75rem;padding:3px 10px;">
                        <i class="bi bi-check2-circle me-1"></i>Save
                    </button>
                </div>
                <!-- Quick-add chips -->
                <div class="px-2 py-2 border-bottom" id="attrQuickAddRow" style="display:none;">
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        <span style="font-size:.7rem;" class="fw-semibold text-muted"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick:</span>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('Fabric Material','SELECT','Cotton, Rayon, Polyester, Linen, Denim, Chiffon, Silk, Blend')">+ Fabric</button>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('GSM / Weight','SELECT','140, 160, 180, 200, 220, 240')">+ GSM</button>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('Sleeve Type','SELECT','Full Sleeve, Half Sleeve, Sleeveless, 3/4th Sleeve')">+ Sleeve</button>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('Collar / Neck','SELECT','Round Neck, V-Neck, Shirt Collar, Mandarin, Polo')">+ Collar</button>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('Fit Type','SELECT','Regular Fit, Slim Fit, Oversized, Relaxed Fit')">+ Fit</button>
                        <button type="button" class="btn-chip" onclick="quickAddAttrRow('Pattern / Print','SELECT','Plain / Solid, Printed, Striped, Checked, Embroidered')">+ Pattern</button>
                    </div>
                </div>
                <!-- Attributes list container -->
                <div class="flex-grow-1 overflow-auto p-0" id="attributesListContainer" style="max-height:520px;min-height:260px;">
                    <div class="text-center py-5 text-muted" id="attrEmptyState">
                        <i class="bi bi-sliders fs-3 opacity-30 d-block mb-2"></i>
                        <div class="small fw-semibold">Select a category above</div>
                        <div style="font-size:.74rem;">Attributes are category-dependent, not global</div>
                    </div>
                    <div id="attributesInlineList"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS -->
<!-- ========================================================================= -->

<!-- MODAL: UNIFIED ADD CATEGORY HIERARCHY & PRE-SET ATTRIBUTES -->
<div class="modal fade" id="modalUnifiedCategory" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0 text-white">
                        <i class="bi bi-diagram-3-fill me-2"></i>Create Category Hierarchy &amp; Pre-set Attributes
                    </h5>
                    <small class="text-white-50">Set up Category, Subcategory, Sub-Subcategory, and base specifications for supplier inwarding</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUnifiedCategory" onsubmit="submitUnifiedCategoryForm(event)">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-primary rounded-pill px-3 py-1">Step 1</span>
                            <h6 class="fw-bold mb-0 text-dark">Category Hierarchy Flow</h6>
                            <span class="text-muted small ms-auto"><span class="text-danger">*</span> Required field</span>
                        </div>
                        <div class="row g-3 p-3 bg-light rounded-3 border">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <span class="badge rounded-circle bg-primary text-white me-1" style="width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;">1</span>
                                    Main Category <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="unifiedCatName" class="form-control form-control-sm text-uppercase fw-semibold" placeholder="e.g. MATERNITY / SHIRTS" required maxlength="150" autofocus>
                                <div class="form-text text-muted" style="font-size:.72rem;">Primary department group</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <span class="badge rounded-circle bg-info text-white me-1" style="width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;">2</span>
                                    Sub Category <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <input type="text" id="unifiedSubcatName" class="form-control form-control-sm text-uppercase" placeholder="e.g. COMPLEMENTARY / FORMAL" maxlength="150">
                                <div class="form-text text-muted" style="font-size:.72rem;">Secondary category level</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-dark mb-1">
                                    <span class="badge rounded-circle bg-success text-white me-1" style="width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;">3</span>
                                    Sub-Sub Category <span class="text-muted fw-normal">(Optional)</span>
                                </label>
                                <input type="text" id="unifiedSubSubcatName" class="form-control form-control-sm text-uppercase" placeholder="e.g. FULL HAND / SLIM FIT" maxlength="150">
                                <div class="form-text text-muted" style="font-size:.72rem;">Specific style classification</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success rounded-pill px-3 py-1">Step 2</span>
                                
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addUnifiedAttributeRow()">
                                <i class="bi bi-plus-lg me-1"></i> Add Attribute
                            </button>
                        </div>
                        <p class="text-muted small mb-2">Configure specifications for this specific category branch only. Attributes are never applied globally.</p>
                        <div class="d-flex align-items-center gap-1 flex-wrap mb-3 p-2 bg-light rounded-2 border">
                            <span class="small fw-semibold text-muted me-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick Add:</span>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('Fabric Material','SELECT','Cotton, Rayon, Polyester, Linen, Denim, Chiffon, Silk, Blend')">+ Fabric</button>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('GSM / Weight','SELECT','140, 160, 180, 200, 220, 240')">+ GSM</button>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('Sleeve Type','SELECT','Full Sleeve, Half Sleeve, Sleeveless, 3/4th Sleeve')">+ Sleeve</button>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('Collar / Neck','SELECT','Round Neck, V-Neck, Shirt Collar, Mandarin, Polo')">+ Collar</button>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('Fit Type','SELECT','Regular Fit, Slim Fit, Oversized, Relaxed Fit')">+ Fit</button>
                            <button type="button" class="btn-chip" onclick="quickAddUnifiedAttr('Pattern / Print','SELECT','Plain / Solid, Printed, Striped, Checked, Embroidered')">+ Pattern</button>
                        </div>
                        <div class="table-responsive border rounded-2">
                            <table class="table table-sm table-bordered align-middle mb-0" id="unifiedAttributesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:150px;">Attribute Name <span class="text-danger">*</span></th>
                                        <th style="width:140px;">Type</th>
                                        <th style="min-width:160px;">Options (for Dropdown)</th>
                                        <th style="width:80px;" class="text-center">Required?</th>
                                        <th style="width:40px;" class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="unifiedAttributesTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                    <span class="text-muted small"><i class="bi bi-info-circle me-1 text-primary"></i>Saved categories immediately display in the list.</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-semibold px-4 shadow-sm" id="btnSubmitUnifiedCat">
                            <i class="bi bi-check2-circle me-1"></i> Save Category Hierarchy
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT CATEGORY -->
<div class="modal fade" id="modalCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalCategoryTitle">Add Main Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCategory" onsubmit="submitCategoryForm(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="modalCatId" value="">
                    <input type="hidden" id="modalCatType" value="NORMAL">
                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" id="modalCatName" class="form-control" placeholder="e.g. MENS WEAR / SHIRTS / ACCESSORIES" required maxlength="150" style="text-transform:uppercase;">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btnSubmitCat">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT SUBCATEGORY -->
<div class="modal fade" id="modalSubcategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalSubcatTitle">Add Subcategory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSubcategory" onsubmit="submitSubcategoryForm(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="modalSubcatParentId" value="">
                    <input type="hidden" id="modalSubcatId" value="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Parent Category</label>
                        <input type="text" id="modalSubcatParentName" class="form-control-plaintext fw-bold text-primary p-0" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Subcategory Name <span class="text-danger">*</span></label>
                        <input type="text" id="modalSubcatName" class="form-control" placeholder="e.g. FORMAL SHIRTS / CASUAL / JEANS" required maxlength="150" style="text-transform:uppercase;">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btnSubmitSubcat">Save Subcategory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADD / EDIT SUB-SUBCATEGORY -->
<div class="modal fade" id="modalSubSubcategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalSubSubcatTitle">Add Sub-Subcategory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSubSubcategory" onsubmit="submitSubSubcategoryForm(event)">
                <div class="modal-body p-4">
                    <input type="hidden" id="modalSubSubcatParentId" value="">
                    <input type="hidden" id="modalSubSubcatId" value="">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Parent Subcategory</label>
                        <input type="text" id="modalSubSubcatParentName" class="form-control-plaintext fw-bold text-primary p-0" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sub-Subcategory Name <span class="text-danger">*</span></label>
                        <input type="text" id="modalSubSubcatName" class="form-control" placeholder="e.g. FULL HAND / SLIM FIT / CREW NECK" required maxlength="150" style="text-transform:uppercase;">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold" id="btnSubmitSubSubcat">Save Sub-Subcategory</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: CONFIGURE ATTRIBUTES (ACCESSIBLE DIRECTLY FROM LIST VIEW) -->
<div class="modal fade" id="modalConfigAttributes" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-warning text-dark py-3 px-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-sliders me-2"></i>Configure Pre-set Attributes
                    </h5>
                    <small class="text-dark-50" id="modalAttrScopeSubtitle">Category-Dependent Scoping Active</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-shield-check fs-5 text-success"></i>
                    <div>
                        <strong>Strict Level Scoping:</strong> Attributes configured here apply specifically to <span class="fw-bold text-primary" id="modalAttrScopeTarget">Selected Branch</span>.
                    </div>
                </div>
                <!-- Quick add chips -->
                <div class="d-flex align-items-center gap-1 flex-wrap mb-3 p-2 bg-light rounded-2 border">
                    <span class="small fw-semibold text-muted me-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Quick Add:</span>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('Fabric Material','SELECT','Cotton, Rayon, Polyester, Linen, Denim, Chiffon, Silk, Blend')">+ Fabric</button>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('GSM / Weight','SELECT','140, 160, 180, 200, 220, 240')">+ GSM</button>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('Sleeve Type','SELECT','Full Sleeve, Half Sleeve, Sleeveless, 3/4th Sleeve')">+ Sleeve</button>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('Collar / Neck','SELECT','Round Neck, V-Neck, Shirt Collar, Mandarin, Polo')">+ Collar</button>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('Fit Type','SELECT','Regular Fit, Slim Fit, Oversized, Relaxed Fit')">+ Fit</button>
                    <button type="button" class="btn-chip" onclick="quickAddModalAttr('Pattern / Print','SELECT','Plain / Solid, Printed, Striped, Checked, Embroidered')">+ Pattern</button>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold small text-dark">Configured Attributes (<span id="modalAttrCount">0</span>)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addModalAttributeRow()">
                        <i class="bi bi-plus-lg me-1"></i> Add Attribute Row
                    </button>
                </div>

                <div class="table-responsive border rounded-2">
                    <table class="table table-sm table-bordered align-middle mb-0" id="tableModalAttributes">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:160px;">Attribute Name <span class="text-danger">*</span></th>
                                <th style="width:130px;">Type</th>
                                <th style="min-width:170px;">Options (for Dropdown)</th>
                                <th style="width:75px;" class="text-center">Required?</th>
                                <th style="width:40px;" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="modalAttributesTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between align-items-center">
                <span class="text-muted small"><i class="bi bi-info-circle text-primary me-1"></i>Changes take effect immediately on inwarding.</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success fw-semibold px-4 shadow-sm" id="btnSaveModalAttrs" onclick="submitModalAttributes()">
                        <i class="bi bi-check2-circle me-1"></i> Save Attributes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT LOGIC -->
<!-- ========================================================================= -->
<script>
const CSRF_TOKEN = '<?= CSRF::token() ?>';
const APP_URL = '<?= rtrim(url(''), '/') ?>';

let categoryTreeData = [];
let currentViewMode = localStorage.getItem('grm_catalog_view_mode') || 'list'; // 'list' or 'columns'
let currentSearchTerm = '';
let currentStatusFilter = 'active';
let currentTypeFilter = 'all';

// Selected state for columns view
let selectedCatId = null, selectedCatName = '';
let selectedSubId = null, selectedSubName = '';
let selectedSubSubId = null, selectedSubSubName = '';
let selectedNode = null;

// Modal attributes state
let modalAttrTarget = { type: null, catId: null, subId: null, sscId: null, path: '' };

function formatCatTypeBadge(type) {
    const t = (type || 'NORMAL').toUpperCase();
    if (t === 'COMPLEMENTARY') {
        return `<span class="badge" style="background:#f3e8ff;color:#7e22ce;border:1px solid #d8b4fe;font-size:0.68rem;font-weight:600;"><i class="bi bi-gift-fill me-1"></i>Complementary</span>`;
    } else if (t === 'SERIES') {
        return `<span class="badge" style="background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;font-size:0.68rem;font-weight:600;"><i class="bi bi-collection-fill me-1"></i>Series</span>`;
    }
    return `<span class="badge bg-light text-secondary border" style="font-size:0.68rem;font-weight:600;">Normal</span>`;
}

document.addEventListener('DOMContentLoaded', () => {
    switchCatalogView(currentViewMode, false);
    reloadCategoryTree();
});

// View Switcher: List View (Image 1 Style) vs Cascading Columns View (Image 2 Style)
function switchCatalogView(mode, persist = true) {
    currentViewMode = mode;
    if (persist) {
        localStorage.setItem('grm_catalog_view_mode', mode);
    }
    const listCnt = document.getElementById('catalogListViewContainer');
    const colsCnt = document.getElementById('catalogColumnsViewContainer');
    const btnList = document.getElementById('btnToggleList');
    const btnCols = document.getElementById('btnToggleColumns');

    if (mode === 'columns') {
        if (listCnt) listCnt.style.display = 'none';
        if (colsCnt) colsCnt.style.display = '';
        if (btnList) { btnList.className = 'btn btn-sm rounded-pill px-3 fw-semibold btn-light text-muted'; }
        if (btnCols) { btnCols.className = 'btn btn-sm rounded-pill px-3 fw-semibold active btn-primary shadow-sm'; }
    } else {
        if (listCnt) listCnt.style.display = '';
        if (colsCnt) colsCnt.style.display = 'none';
        if (btnList) { btnList.className = 'btn btn-sm rounded-pill px-3 fw-semibold active btn-primary shadow-sm'; }
        if (btnCols) { btnCols.className = 'btn btn-sm rounded-pill px-3 fw-semibold btn-light text-muted'; }
    }
}

// Global Search & Filters
function handleGlobalSearch(term) {
    currentSearchTerm = (term || '').toLowerCase().trim();
    applyFilters();
}

function applyStatusFilter(status) {
    currentStatusFilter = status;
    applyFilters();
}

function applyTypeFilter(type) {
    currentTypeFilter = type;
    applyFilters();
}

function applyFilters() {
    renderCategoryListTable();
    ['categoriesListContainer', 'subcategoriesListContainer', 'subSubcategoriesListContainer'].forEach(id => {
        filterColList(id, currentSearchTerm);
    });
}

function resetFilters() {
    currentSearchTerm = '';
    currentStatusFilter = 'active';
    currentTypeFilter = 'all';
    const sInput = document.getElementById('globalCatalogSearch'); if (sInput) sInput.value = '';
    const stSelect = document.getElementById('catalogStatusFilter'); if (stSelect) stSelect.value = 'active';
    const tpSelect = document.getElementById('catalogTypeFilter'); if (tpSelect) tpSelect.value = 'all';
    ['filterCatInput', 'filterSubInput', 'filterSubSubInput'].forEach(id => {
        const el = document.getElementById(id); if (el) el.value = '';
    });
    document.querySelectorAll('.col-list-item').forEach(el => el.style.display = '');
    reloadCategoryTree();
}

function filterColList(containerId, query) {
    const term = (query || '').toLowerCase().trim();
    document.querySelectorAll(`#${containerId} .col-list-item`).forEach(el => {
        el.style.display = (!term || (el.getAttribute('data-search-text') || el.textContent.toLowerCase()).includes(term)) ? '' : 'none';
    });
}

// Reload Category Tree Data from Server
function reloadCategoryTree(tCat = null, tSub = null, tSsc = null) {
    const tbody = document.getElementById('catalogListTableBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div class="small fw-semibold">Loading Category Hierarchy Flow...</div></td></tr>`;
    }
    const catCol = document.getElementById('categoriesListContainer');
    if (catCol) {
        catCol.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary mb-2"></div><div class="small">Loading categories...</div></div>`;
    }

    fetch(`${APP_URL}/api/warehouse/catalog/category-tree`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.tree) {
                categoryTreeData = data.tree;
                updateKpiCounts();
                renderCategoryListTable();
                renderCategoriesList(tCat, tSub, tSsc);
            } else {
                const msg = data.message || 'Failed to load categories';
                if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger small">${msg}</td></tr>`;
                if (catCol) catCol.innerHTML = `<div class="alert alert-danger small p-2 m-2">${msg}</div>`;
            }
        })
        .catch(err => {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger small">${err.message}</td></tr>`;
            if (catCol) catCol.innerHTML = `<div class="alert alert-danger small p-2 m-2">${err.message}</div>`;
        });
}

function updateKpiCounts() {
    let cats = categoryTreeData.length, subs = 0, sscs = 0, attrs = 0;
    categoryTreeData.forEach(c => {
        attrs += (c.attr_count || (c.attributes || []).length || 0);
        (c.subcategories || []).forEach(s => {
            subs++;
            attrs += (s.attr_count || (s.attributes || []).length || 0);
            (s.sub_subcategories || []).forEach(ss => {
                sscs++;
                attrs += (ss.attr_count || (ss.attributes || []).length || 0);
            });
        });
    });
    [['kpiCatCount', cats], ['kpiSubCount', subs], ['kpiSubSubCount', sscs], ['kpiAttrCount', attrs]].forEach(([id, v]) => {
        const el = document.getElementById(id); if (el) el.textContent = v;
    });
}

function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function inArr(n, h) { return Array.isArray(h) && h.includes(n); }

// =========================================================================
// RENDER VIEW 1: CATEGORY HIERARCHY FLOW IN LIST VIEW (IMAGE 1 UI STYLE)
// =========================================================================
function renderCategoryListTable() {
    const tbody = document.getElementById('catalogListTableBody');
    if (!tbody) return;

    if (!categoryTreeData || categoryTreeData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
            <i class="bi bi-folder-x fs-1 opacity-40 d-block mb-2"></i>
            <div class="fw-bold">No Categories Configured</div>
            <p class="small text-muted mb-3">Get started by creating your primary department category.</p>
            <button class="btn btn-primary btn-sm fw-semibold" onclick="openAddCategoryModal()"><i class="bi bi-plus-circle me-1"></i> Add Category</button>
        </td></tr>`;
        return;
    }

    // Filter by search term & category type
    const term = currentSearchTerm;
    const typeFilter = currentTypeFilter;
    const filtered = categoryTreeData.filter(cat => {
        if (typeFilter && typeFilter !== 'all') {
            const catType = (cat.category_type || 'NORMAL').toUpperCase();
            if (catType !== typeFilter.toUpperCase()) return false;
        }
        if (!term) return true;
        if (cat.name.toLowerCase().includes(term) || (cat.code && cat.code.toLowerCase().includes(term))) return true;
        const subMatch = (cat.subcategories || []).some(s => {
            if (s.name.toLowerCase().includes(term) || (s.code && s.code.toLowerCase().includes(term))) return true;
            return (s.sub_subcategories || []).some(ss => ss.name.toLowerCase().includes(term) || (ss.code && ss.code.toLowerCase().includes(term)));
        });
        if (subMatch) return true;
        // Search attributes
        const attrMatch = (cat.attributes || []).some(a => a.attr_name.toLowerCase().includes(term));
        return attrMatch;
    });

    const sumEl = document.getElementById('catalogListSummaryText');
    if (sumEl) sumEl.textContent = `Showing ${filtered.length} of ${categoryTreeData.length} Category Hierarchies`;

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">
            <i class="bi bi-search fs-2 opacity-30 d-block mb-2"></i>
            <div class="fw-semibold">No matching categories found</div>
            <div class="small">Try resetting your search query or status filter.</div>
        </td></tr>`;
        return;
    }

    let html = '';
    filtered.forEach((cat, idx) => {
        const subcats = cat.subcategories || [];
        const catAttrs = cat.attributes || [];
        
        // Collect all sub-sub categories across this category
        const allSscs = [];
        subcats.forEach(s => {
            (s.sub_subcategories || []).forEach(ss => {
                allSscs.push({ ...ss, parentSubName: s.name });
            });
        });

        // Collect all attributes across the entire category hierarchy
        const branchAttrs = [...catAttrs];
        subcats.forEach(s => {
            (s.attributes || []).forEach(a => branchAttrs.push(a));
            (s.sub_subcategories || []).forEach(ss => {
                (ss.attributes || []).forEach(a => branchAttrs.push(a));
            });
        });

        const rowId = `cat_row_${cat.id}`;
        const expandId = `cat_expand_${cat.id}`;

        html += `
        <tr class="cat-table-row" id="${rowId}" style="border-bottom:1px solid #f1f5f9; cursor:pointer;" onclick="toggleListRowExpand('${expandId}', event)">
            <!-- 1. CATEGORY / DEPARTMENT -->
            <td style="padding:14px 18px;">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-sm btn-light p-0 d-flex align-items-center justify-content-center text-muted expand-chevron-btn" style="width:24px;height:24px;border-radius:4px;" title="Expand hierarchy branch">
                        <i class="bi bi-chevron-right fs-6" id="chev_${cat.id}"></i>
                    </button>
                    <div class="item-icon-badge" style="background:#e0e7ff; color:#4338ca;">
                        <i class="bi bi-folder-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-dark fs-6" style="letter-spacing:0.3px;">${esc(cat.name)}</span>
                            ${cat.code ? `<span class="code-badge fw-bold" style="background:#eef2ff;color:#4f46e5;border-color:#c7d2fe;">${esc(cat.code)}</span>` : ''}
                            ${formatCatTypeBadge(cat.category_type)}
                        </div>
                        <div class="text-muted small mt-0.5 d-flex align-items-center gap-2">
                            <span><i class="bi bi-diagram-2 me-1 text-primary"></i>${subcats.length} Subcategor${subcats.length === 1 ? 'y' : 'ies'}</span>
                            <span>•</span>
                            <span><i class="bi bi-tags me-1 text-info"></i>${allSscs.length} Style Classifications</span>
                        </div>
                    </div>
                </div>
            </td>

            <!-- 2. SUB CATEGORIES -->
            <td style="padding:14px 14px;">
                ${subcats.length > 0 ? `
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        ${subcats.slice(0, 4).map(s => `
                            <span class="badge bg-light text-primary border border-primary-subtle px-2 py-1" style="font-size:0.75rem; font-weight:600;">
                                <i class="bi bi-folder2 me-1"></i>${esc(s.name)}
                                ${(s.sub_subcategories || []).length > 0 ? `<span class="badge bg-primary text-white rounded-pill ms-1" style="font-size:0.65rem;">${(s.sub_subcategories || []).length}</span>` : ''}
                            </span>
                        `).join('')}
                        ${subcats.length > 4 ? `<span class="badge bg-secondary-subtle text-secondary" style="font-size:0.72rem;">+${subcats.length - 4} more</span>` : ''}
                    </div>
                ` : `<span class="text-muted small" style="font-style:italic;"><i class="bi bi-dash-circle me-1"></i>No subcategories</span>`}
            </td>

            <!-- 3. SUB-SUB CATEGORIES -->
            <td style="padding:14px 14px;">
                ${allSscs.length > 0 ? `
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        ${allSscs.slice(0, 4).map(ss => `
                            <span class="badge bg-light text-success border border-success-subtle px-2 py-1" style="font-size:0.75rem; font-weight:600;">
                                <i class="bi bi-tag me-1"></i>${esc(ss.name)}
                            </span>
                        `).join('')}
                        ${allSscs.length > 4 ? `<span class="badge bg-secondary-subtle text-secondary" style="font-size:0.72rem;">+${allSscs.length - 4} more</span>` : ''}
                    </div>
                ` : `<span class="text-muted small" style="font-style:italic;"><i class="bi bi-dash-circle me-1"></i>No style specs</span>`}
            </td>

            <!-- 4. PRE-SET ATTRIBUTES (SCOPED) -->
            <td style="padding:14px 14px;">
                ${branchAttrs.length > 0 ? `
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        ${branchAttrs.slice(0, 3).map(a => `
                            <span class="pill-attr" style="font-size:0.72rem;">
                                <i class="bi bi-sliders me-1"></i>${esc(a.attr_name)}
                                <small class="text-muted ms-0.5">(${esc(a.attr_type)})</small>
                            </span>
                        `).join('')}
                        ${branchAttrs.length > 3 ? `<span class="badge bg-warning-subtle text-dark border border-warning" style="font-size:0.7rem;">+${branchAttrs.length - 3}</span>` : ''}
                    </div>
                ` : `
                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-warning fw-semibold" style="font-size:0.76rem;" onclick="openConfigAttributesModal(event, 'cat', ${cat.id}, null, null, '${esc(cat.name)}')">
                        <i class="bi bi-plus-circle me-1"></i>Set attributes
                    </button>
                `}
            </td>

            <!-- 5. STATUS -->
            <td class="text-center" style="padding:14px 12px;">
                <span class="pill-active">Active</span>
            </td>

            <!-- 6. ACTIONS (MATCHING IMAGE 1) -->
            <td class="text-end" style="padding:14px 18px;" onclick="event.stopPropagation()">
                <div class="d-inline-flex align-items-center gap-1">
                    <button type="button" class="icon-btn" onclick="openAddSubcategoryModal(event, ${cat.id}, '${esc(cat.name)}')" title="Add Subcategory under ${esc(cat.name)}">
                        <i class="bi bi-folder-plus text-primary"></i>
                    </button>
                    <button type="button" class="icon-btn" onclick="openConfigAttributesModal(event, 'cat', ${cat.id}, null, null, '${esc(cat.name)}')" title="Configure Scoped Pre-set Attributes">
                        <i class="bi bi-sliders text-warning"></i>
                    </button>
                    <button type="button" class="icon-btn" onclick="openEditCategoryModal(event, ${cat.id}, '${esc(cat.name)}', '${esc(cat.category_type || 'NORMAL')}')" title="Edit Category">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="icon-btn danger" onclick="deleteCategory(event, ${cat.id}, '${esc(cat.name)}')" title="Delete Category">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>

        <!-- EXPANDABLE ACCORDION ROW: FULL BRANCH BREAKDOWN -->
        <tr class="cat-expand-row" id="${expandId}" style="display:none; background:#f8fafc;">
            <td colspan="6" style="padding:18px 24px; border-bottom:2px solid #e2e8f0;">
                <div class="p-3 bg-white rounded-3 border shadow-xs">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary text-white"><i class="bi bi-diagram-3 me-1"></i>Hierarchy Tree for "${esc(cat.name)}"</span>
                            <span class="text-muted small">Category-Scoped Flow: Category &rsaquo; Sub Category &rsaquo; Sub-Sub Category &amp; Attributes</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openAddSubcategoryModal(event, ${cat.id}, '${esc(cat.name)}')">
                                <i class="bi bi-plus-lg me-1"></i> Add Subcategory
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="openConfigAttributesModal(event, 'cat', ${cat.id}, null, null, '${esc(cat.name)}')">
                                <i class="bi bi-sliders me-1"></i> Edit Cat Attributes (${catAttrs.length})
                            </button>
                        </div>
                    </div>

                    ${subcats.length === 0 ? `
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-folder-plus fs-2 opacity-40 d-block mb-1"></i>
                            <div class="small fw-semibold">No Subcategories exist yet for "${esc(cat.name)}"</div>
                            <button class="btn btn-sm btn-primary mt-2" onclick="openAddSubcategoryModal(event, ${cat.id}, '${esc(cat.name)}')">+ Add First Subcategory</button>
                        </div>
                    ` : `
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle mb-0">
                                <thead>
                                    <tr class="text-muted small border-bottom" style="font-size:0.75rem;">
                                        <th style="width:28%;">Sub Category (Level 2)</th>
                                        <th style="width:34%;">Sub-Sub Categories &amp; Style Specs (Level 3)</th>
                                        <th style="width:22%;">Branch Attributes</th>
                                        <th class="text-end" style="width:16%;">Sub Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${subcats.map(sub => {
                                        const sscs = sub.sub_subcategories || [];
                                        const subAttrs = sub.attributes || [];
                                        return `
                                        <tr style="border-bottom:1px dashed #e2e8f0;">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="bi bi-arrow-return-right text-muted"></i>
                                                    <div class="item-icon-badge" style="width:26px;height:26px;background:#cffafe;color:#0891b2;font-size:0.8rem;">
                                                        <i class="bi bi-folder2-open"></i>
                                                    </div>
                                                    <div>
                                                        <span class="fw-bold text-dark" style="font-size:0.83rem;">${esc(sub.name)}</span>
                                                        ${sub.code ? `<span class="code-badge ms-1">${esc(sub.code)}</span>` : ''}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                                    ${sscs.map(ss => `
                                                        <span class="badge bg-light text-success border border-success-subtle px-2 py-1" style="font-size:0.72rem;">
                                                            <i class="bi bi-tag-fill me-1"></i>${esc(ss.name)}
                                                            ${(ss.attributes || []).length > 0 ? `<span class="badge bg-warning text-dark ms-1">${(ss.attributes || []).length} attrs</span>` : ''}
                                                        </span>
                                                    `).join('')}
                                                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 text-success fw-bold ms-1" style="font-size:0.72rem;" onclick="openAddSubSubcategoryModal(event, ${sub.id}, '${esc(sub.name)}')">
                                                        + Add Style
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                ${subAttrs.length > 0 ? `
                                                    <div class="d-flex flex-wrap gap-1">
                                                        ${subAttrs.map(a => `<span class="pill-attr" style="font-size:0.7rem;">${esc(a.attr_name)}</span>`).join('')}
                                                    </div>
                                                ` : `<span class="text-muted" style="font-size:0.72rem;font-style:italic;">None</span>`}
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="icon-btn" onclick="openAddSubSubcategoryModal(event, ${sub.id}, '${esc(sub.name)}')" title="Add Sub-Subcategory">
                                                        <i class="bi bi-tag text-success"></i>
                                                    </button>
                                                    <button type="button" class="icon-btn" onclick="openConfigAttributesModal(event, 'sub', ${cat.id}, ${sub.id}, null, '${esc(cat.name)} &rsaquo; ${esc(sub.name)}')" title="Configure Subcategory Attributes">
                                                        <i class="bi bi-sliders text-warning"></i>
                                                    </button>
                                                    <button type="button" class="icon-btn" onclick="openEditSubcategoryModal(event, ${sub.id}, '${esc(sub.name)}', ${cat.id}, '${esc(cat.name)}')" title="Edit Subcategory">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="icon-btn danger" onclick="deleteSubcategory(event, ${sub.id}, '${esc(sub.name)}')" title="Delete Subcategory">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `}
                </div>
            </td>
        </tr>
        `;
    });

    tbody.innerHTML = html;
}

function toggleListRowExpand(expandId, e) {
    if (e && (e.target.closest('button') || e.target.closest('a') || e.target.closest('input') || e.target.closest('select'))) {
        return;
    }
    const row = document.getElementById(expandId);
    if (!row) return;
    const isHidden = row.style.display === 'none';
    row.style.display = isHidden ? '' : 'none';

    const catId = expandId.replace('cat_expand_', '');
    const chev = document.getElementById(`chev_${catId}`);
    if (chev) {
        chev.className = isHidden ? 'bi bi-chevron-down fs-6 text-primary' : 'bi bi-chevron-right fs-6';
    }
}

// =========================================================================
// RENDER VIEW 2: CASCADING COLUMNS VIEW (IMAGE 2 UI STYLE)
// =========================================================================
function renderCategoriesList(tCat = null, tSub = null, tSsc = null) {
    const cnt = document.getElementById('categoriesListContainer');
    const badge = document.getElementById('badgeCatCount'); if (badge) badge.textContent = categoryTreeData.length;
    if (!categoryTreeData || !categoryTreeData.length) {
        if (cnt) cnt.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 opacity-50 d-block mb-2"></i><div class="fw-semibold">No Categories Configured</div><button class="btn btn-sm btn-primary mt-2" onclick="openAddCategoryModal()">+ Add Category</button></div>';
        resetSubCol(); resetSubSubCol(); resetAttrCol(); return;
    }
    if (cnt) {
        cnt.innerHTML = '<div class="d-flex flex-column">' + categoryTreeData.map(cat => {
            const active = selectedCatId === cat.id;
            const sc = (cat.subcategories || []).length;
            return `<div class="col-list-item${active ? ' active-col-item' : ''}" data-cat-id="${cat.id}" data-search-text="${esc(cat.name).toLowerCase()}" onclick="onSelectCategory(${cat.id},'${esc(cat.name)}')">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                        <div class="item-icon-badge${active ? ' active' : ''}"><i class="bi bi-folder-fill ${active ? 'text-primary' : 'text-warning'}"></i></div>
                        <div class="text-truncate">
                            <div class="d-flex align-items-center gap-1"><span class="fw-bold text-truncate item-title" style="font-size:.82rem;">${esc(cat.name)}</span>${cat.code ? `<span class="code-badge">${esc(cat.code)}</span>` : ''}</div>
                            <div class="d-flex align-items-center gap-1 mt-1">${formatCatTypeBadge(cat.category_type)}<span class="pill-active">Active</span><span class="pill-info">${sc} Subcats</span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <button type="button" class="icon-btn" onclick="openEditCategoryModal(event,${cat.id},'${esc(cat.name)}','${esc(cat.category_type || 'NORMAL')}')" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="icon-btn danger" onclick="deleteCategory(event,${cat.id},'${esc(cat.name)}')" title="Delete"><i class="bi bi-trash"></i></button>
                        ${active ? '<i class="bi bi-chevron-right text-primary" style="font-size:.75rem;"></i>' : ''}
                    </div>
                </div>
            </div>`;
        }).join('') + '</div>';
    }
    const toSel = tCat || selectedCatId;
    if (toSel) { const f = categoryTreeData.find(c => c.id === toSel); if (f) { onSelectCategory(f.id, f.name, false, tSub, tSsc); return; } }
    if (categoryTreeData.length > 0) onSelectCategory(categoryTreeData[0].id, categoryTreeData[0].name, false);
}

function onSelectCategory(catId, catName, user = true, tSub = null, tSsc = null) {
    selectedCatId = catId; selectedCatName = catName; selectedSubId = null; selectedSubName = ''; selectedSubSubId = null; selectedSubSubName = '';
    document.querySelectorAll('#categoriesListContainer .col-list-item').forEach(el => {
        const a = parseInt(el.getAttribute('data-cat-id')) === catId;
        el.classList.toggle('active-col-item', a);
        const ib = el.querySelector('.item-icon-badge'), ic = el.querySelector('i.bi-folder-fill');
        if (ib) ib.classList.toggle('active', a);
        if (ic) ic.className = 'bi bi-folder-fill ' + (a ? 'text-primary' : 'text-warning');
    });
    const t2 = document.getElementById('col2HeaderTitle'); if (t2) t2.textContent = `Sub Category (${catName})`;
    const ba = document.getElementById('btnAddSubcatBtn'); if (ba) ba.disabled = false;
    const cat = categoryTreeData.find(c => c.id === catId);
    renderSubcategoriesList(cat ? (cat.subcategories || []) : [], tSub, tSsc);
    if (!tSub) syncAttributesTool('cat', catId, null, null, catName);
}

function renderSubcategoriesList(subcats, tSub = null, tSsc = null) {
    const cnt = document.getElementById('subcategoriesListContainer');
    const badge = document.getElementById('badgeSubCount'); if (badge) badge.textContent = subcats.length;
    if (!subcats || !subcats.length) {
        if (cnt) cnt.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-folder-plus fs-2 opacity-50 d-block mb-2"></i><div class="small fw-semibold">No Subcategories in "${esc(selectedCatName)}"</div><button class="btn btn-sm btn-primary mt-2" onclick="openAddSubcatForSelected()">+ Add Subcategory</button></div>`;
        resetSubSubCol();
        return;
    }
    if (cnt) {
        cnt.innerHTML = '<div class="d-flex flex-column">' + subcats.map(sub => {
            const active = selectedSubId === sub.id, sc = (sub.sub_subcategories || []).length;
            return `<div class="col-list-item${active ? ' active-col-item-info' : ''}" data-sub-id="${sub.id}" data-search-text="${esc(sub.name).toLowerCase()}" onclick="onSelectSubcategory(${sub.id},'${esc(sub.name)}')">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                        <div class="item-icon-badge${active ? ' active-info' : ''}"><i class="bi bi-folder2-open ${active ? 'text-info' : 'text-primary'}"></i></div>
                        <div class="text-truncate">
                            <div class="d-flex align-items-center gap-1"><span class="fw-bold text-truncate item-title" style="font-size:.82rem;">${esc(sub.name)}</span>${sub.code ? `<span class="code-badge">${esc(sub.code)}</span>` : ''}</div>
                            <div class="d-flex gap-1 mt-1"><span class="pill-active">Active</span><span class="pill-info">${sc} Sub-Subs</span></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <button type="button" class="icon-btn" onclick="openEditSubcategoryModal(event,${sub.id},'${esc(sub.name)}',${selectedCatId},'${esc(selectedCatName)}')" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="icon-btn danger" onclick="deleteSubcategory(event,${sub.id},'${esc(sub.name)}')" title="Delete"><i class="bi bi-trash"></i></button>
                        ${active ? '<i class="bi bi-chevron-right text-info" style="font-size:.75rem;"></i>' : ''}
                    </div>
                </div>
            </div>`;
        }).join('') + '</div>';
    }
    if (tSub) { const f = subcats.find(s => s.id === tSub); if (f) { onSelectSubcategory(f.id, f.name, false, tSsc); return; } }
    if (subcats.length > 0) onSelectSubcategory(subcats[0].id, subcats[0].name, false);
}

function onSelectSubcategory(subId, subName, user = true, tSsc = null) {
    selectedSubId = subId; selectedSubName = subName; selectedSubSubId = null; selectedSubSubName = '';
    document.querySelectorAll('#subcategoriesListContainer .col-list-item').forEach(el => {
        el.classList.toggle('active-col-item-info', parseInt(el.getAttribute('data-sub-id')) === subId);
    });
    const t3 = document.getElementById('col3HeaderTitle'); if (t3) t3.textContent = `Sub-Sub (${subName})`;
    const ba = document.getElementById('btnAddSubSubcatBtn'); if (ba) ba.disabled = false;
    const cat = categoryTreeData.find(c => c.id === selectedCatId);
    const sub = cat ? (cat.subcategories || []).find(s => s.id === subId) : null;
    renderSubSubcategoriesList(sub ? (sub.sub_subcategories || []) : [], tSsc);
    if (!tSsc) syncAttributesTool('sub', selectedCatId, subId, null, `${selectedCatName} \u203a ${subName}`);
}

function renderSubSubcategoriesList(sscs, tSsc = null) {
    const cnt = document.getElementById('subSubcategoriesListContainer');
    const badge = document.getElementById('badgeSubSubCount'); if (badge) badge.textContent = sscs.length;
    if (!sscs || !sscs.length) {
        if (cnt) cnt.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-tag fs-2 opacity-50 d-block mb-2"></i><div class="small fw-semibold">No Sub-Subcategories in "${esc(selectedSubName)}"</div><button class="btn btn-sm btn-primary mt-2" onclick="openAddSubSubcatForSelected()">+ Add Sub-Subcategory</button></div>`;
        return;
    }
    if (cnt) {
        cnt.innerHTML = '<div class="d-flex flex-column">' + sscs.map(ssc => {
            const active = selectedSubSubId === ssc.id;
            return `<div class="col-list-item${active ? ' active-col-item-success' : ''}" data-ssc-id="${ssc.id}" data-search-text="${esc(ssc.name).toLowerCase()}" onclick="onSelectSubSubcategory(${ssc.id},'${esc(ssc.name)}')">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2 text-truncate me-2">
                        <div class="item-icon-badge${active ? ' active-success' : ''}"><i class="bi bi-tag-fill ${active ? 'text-success' : 'text-dark'}"></i></div>
                        <div class="text-truncate">
                            <div class="d-flex align-items-center gap-1"><span class="fw-bold text-truncate item-title" style="font-size:.82rem;">${esc(ssc.name)}</span>${ssc.code ? `<span class="code-badge">${esc(ssc.code)}</span>` : ''}</div>
                            <div class="d-flex gap-1 mt-1"><span class="pill-active">Active</span>${ssc.attr_count > 0 ? `<span class="pill-attr"><i class="bi bi-sliders me-1"></i>${ssc.attr_count} attrs</span>` : ''}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                        <button type="button" class="icon-btn" onclick="openEditSubSubcategoryModal(event,${ssc.id},'${esc(ssc.name)}',${selectedSubId},'${esc(selectedSubName)}')" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="icon-btn danger" onclick="deleteSubSubcategory(event,${ssc.id},'${esc(ssc.name)}')" title="Delete"><i class="bi bi-trash"></i></button>
                        ${active ? '<i class="bi bi-chevron-right text-success" style="font-size:.75rem;"></i>' : ''}
                    </div>
                </div>
            </div>`;
        }).join('') + '</div>';
    }
    if (tSsc) { const f = sscs.find(s => s.id === tSsc); if (f) { onSelectSubSubcategory(f.id, f.name, false); return; } }
    if (sscs.length > 0) onSelectSubSubcategory(sscs[0].id, sscs[0].name, false);
}

function onSelectSubSubcategory(sscId, sscName) {
    selectedSubSubId = sscId; selectedSubSubName = sscName;
    document.querySelectorAll('#subSubcategoriesListContainer .col-list-item').forEach(el => {
        el.classList.toggle('active-col-item-success', parseInt(el.getAttribute('data-ssc-id')) === sscId);
    });
    syncAttributesTool('subsub', selectedCatId, selectedSubId, sscId, `${selectedCatName} \u203a ${selectedSubName} \u203a ${sscName}`);
}

function resetSubCol() {
    selectedSubId = null; selectedSubName = '';
    const c = document.getElementById('subcategoriesListContainer'); if (c) c.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-arrow-left-circle fs-3 opacity-50 d-block mb-2"></i><div class="small fw-semibold">Select a Category first</div></div>';
    const b = document.getElementById('badgeSubCount'); if (b) b.textContent = '0';
    const btn = document.getElementById('btnAddSubcatBtn'); if (btn) btn.disabled = true;
    const t = document.getElementById('col2HeaderTitle'); if (t) t.textContent = 'Sub Category';
}

function resetSubSubCol() {
    selectedSubSubId = null; selectedSubSubName = '';
    const c = document.getElementById('subSubcategoriesListContainer'); if (c) c.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-arrow-left-circle fs-3 opacity-50 d-block mb-2"></i><div class="small fw-semibold">Select a Sub Category first</div></div>';
    const b = document.getElementById('badgeSubSubCount'); if (b) b.textContent = '0';
    const btn = document.getElementById('btnAddSubSubcatBtn'); if (btn) btn.disabled = true;
    const t = document.getElementById('col3HeaderTitle'); if (t) t.textContent = 'Sub-Sub Category';
}

function resetAttrCol() {
    selectedNode = null;
    const list = document.getElementById('attributesInlineList'); if (list) list.innerHTML = '';
    const em = document.getElementById('attrEmptyState'); if (em) em.style.display = '';
    const b = document.getElementById('attrCountBadge'); if (b) b.textContent = '--';
    const s = document.getElementById('labelActiveBranchScope'); if (s) s.textContent = 'Select any category to configure';
    const add = document.getElementById('btnAddAttrRow'); if (add) add.disabled = true;
    const save = document.getElementById('btnSaveAttrs'); if (save) save.disabled = true;
    const q = document.getElementById('attrQuickAddRow'); if (q) q.style.display = 'none';
}

function openAddSubcatForSelected() {
    if (!selectedCatId) { alert('Please select a Category first.'); return; }
    openAddSubcategoryModal(null, selectedCatId, selectedCatName);
}

function openAddSubSubcatForSelected() {
    if (!selectedSubId) { alert('Please select a Subcategory first.'); return; }
    openAddSubSubcategoryModal(null, selectedSubId, selectedSubName);
}

// =========================================================================
// ATTRIBUTES INLINE MANAGER (FOR COLUMNS VIEW)
// =========================================================================
function syncAttributesTool(type, catId, subId, sscId, path) {
    selectedNode = { type, catId, subId, sscId, path };
    const s = document.getElementById('labelActiveBranchScope'); if (s) s.textContent = path;
    const t = document.getElementById('col4HeaderTitle');
    if (t) t.innerHTML = `<i class="bi bi-sliders me-1 text-warning"></i>${type === 'subsub' ? 'Sub-Sub Attrs' : type === 'sub' ? 'Subcat Attrs' : 'Cat Attrs'}`;
    const add = document.getElementById('btnAddAttrRow'); if (add) add.disabled = false;
    const save = document.getElementById('btnSaveAttrs'); if (save) save.disabled = false;
    const q = document.getElementById('attrQuickAddRow'); if (q) q.style.display = '';
    loadAttributesInline(catId, subId, sscId);
}

function loadAttributesInline(catId, subId, sscId) {
    const list = document.getElementById('attributesInlineList');
    const em = document.getElementById('attrEmptyState'); if (em) em.style.display = 'none';
    if (list) list.innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-warning mb-1"></div><div class="small">Loading...</div></div>';
    let url = `${APP_URL}/api/warehouse/catalog/attributes/${catId}?exact=1`;
    if (subId) url += `&subcategory_id=${subId}`;
    if (sscId) url += `&sub_subcategory_id=${sscId}`;
    fetch(url).then(r => r.json()).then(data => {
        if (list) list.innerHTML = '';
        const badge = document.getElementById('attrCountBadge');
        if (data.success && data.attributes && data.attributes.length > 0) {
            if (badge) badge.textContent = data.attributes.length;
            data.attributes.forEach(a => renderAttrRow(a));
        } else {
            if (badge) badge.textContent = '0';
            if (list) list.innerHTML = '<div class="text-center py-4 text-muted" id="noAttrsPlaceholder"><i class="bi bi-plus-circle-dotted fs-3 opacity-30 d-block mb-2"></i><div class="small">No attributes defined yet.</div><div style="font-size:.72rem;">Click <strong>+ Add</strong> or use Quick chips.</div></div>';
        }
    }).catch(err => { if (list) list.innerHTML = `<div class="text-danger small p-3">${err.message}</div>`; });
}

function renderAttrRow(attr) {
    const list = document.getElementById('attributesInlineList');
    const div = document.createElement('div'); div.className = 'attr-row';
    const a = attr || { attr_name: '', attr_type: 'SELECT', attr_options: '', is_required: 0 };
    div.innerHTML = `<div class="attr-row-inner">
        <div class="ar-name"><input type="text" class="form-control form-control-sm attr-name fw-semibold" value="${esc(a.attr_name || '')}" placeholder="Attribute Name" required></div>
        <div class="ar-type"><select class="form-select form-select-sm attr-type">
            <option value="SELECT" selected>Dropdown</option>
        </select></div>
        <div class="ar-opts">
            <input type="text" class="form-control form-control-sm attr-opts" value="${esc(a.attr_options || '')}" placeholder="e.g. S, M, L, XL or Red, Blue" required>
        </div>
        <div class="ar-req text-center"><div class="form-check form-switch d-inline-block mb-0"><input class="form-check-input attr-req" type="checkbox" ${a.is_required ? 'checked' : ''}></div></div>
        <div class="ar-del text-center"><button type="button" class="icon-btn danger" onclick="this.closest('.attr-row').remove();updateAttrBadge();" title="Remove"><i class="bi bi-trash"></i></button></div>
    </div>`;
    list.appendChild(div);
}

function updateAttrBadge() {
    const c = document.querySelectorAll('#attributesInlineList .attr-row').length;
    const b = document.getElementById('attrCountBadge'); if (b) b.textContent = c;
}

function addAttributeRow(preset = null) {
    const p = document.getElementById('noAttrsPlaceholder'); if (p) p.remove();
    const em = document.getElementById('attrEmptyState'); if (em) em.style.display = 'none';
    renderAttrRow(preset || { attr_name: '', attr_type: 'SELECT', attr_options: '', is_required: 0 });
    updateAttrBadge();
}

function quickAddAttrRow(name, type, opts, req = 0) {
    const ex = Array.from(document.querySelectorAll('#attributesInlineList .attr-name')).find(i => i.value.trim().toLowerCase() === name.toLowerCase());
    if (ex) { ex.focus(); ex.classList.add('is-valid'); setTimeout(() => ex.classList.remove('is-valid'), 1000); return; }
    addAttributeRow({ attr_name: name, attr_type: type || 'SELECT', attr_options: opts, is_required: req });
}

function handleAttrType(sel) {
    // Dropdown is only type available
}

function saveCategoryAttributes() {
    if (!selectedNode) return;
    const btn = document.getElementById('btnSaveAttrs');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const attrs = [];
    document.querySelectorAll('#attributesInlineList .attr-row').forEach((r, i) => {
        const n = r.querySelector('.attr-name').value.trim(); if (!n) return;
        attrs.push({ attr_name: n, attr_type: r.querySelector('.attr-type').value, attr_options: r.querySelector('.attr-opts').value.trim(), is_required: r.querySelector('.attr-req').checked ? 1 : 0, sort_order: i + 1 });
    });
    const p = new URLSearchParams();
    p.append('_csrf_token', CSRF_TOKEN); p.append('category_id', selectedNode.catId);
    if (selectedNode.subId) p.append('subcategory_id', selectedNode.subId);
    if (selectedNode.sscId) p.append('sub_subcategory_id', selectedNode.sscId);
    p.append('attributes', JSON.stringify(attrs));
    fetch(`${APP_URL}/api/warehouse/catalog/attributes/save`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            btn.disabled = false; btn.innerHTML = orig;
            if (data.success) { showToast('Saved', `Attributes saved for "${selectedNode.path}"!`, 'success'); reloadCategoryTree(selectedNode.catId, selectedNode.subId, selectedNode.sscId); }
            else showToast('Error', data.message || 'Error saving', 'danger');
        }).catch(err => { btn.disabled = false; btn.innerHTML = orig; showToast('Error', err.message, 'danger'); });
}

// =========================================================================
// MODAL ATTRIBUTES MANAGER (FOR LIST VIEW DIRECT CONFIGURATION)
// =========================================================================
function openConfigAttributesModal(e, type, catId, subId, sscId, path) {
    if (e) e.stopPropagation();
    modalAttrTarget = { type, catId, subId, sscId, path };
    document.getElementById('modalAttrScopeTarget').textContent = path;
    document.getElementById('modalAttrScopeSubtitle').textContent = `Target: ${path}`;
    document.getElementById('modalAttributesTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-warning mb-1"></div><div>Loading existing attributes...</div></td></tr>';
    new bootstrap.Modal(document.getElementById('modalConfigAttributes')).show();

    let url = `${APP_URL}/api/warehouse/catalog/attributes/${catId}?exact=1`;
    if (subId) url += `&subcategory_id=${subId}`;
    if (sscId) url += `&sub_subcategory_id=${sscId}`;

    fetch(url).then(r => r.json()).then(data => {
        const tb = document.getElementById('modalAttributesTableBody');
        tb.innerHTML = '';
        if (data.success && data.attributes && data.attributes.length > 0) {
            data.attributes.forEach(a => addModalAttributeRow(a));
        } else {
            tb.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted" id="modalNoAttrsRow">No attributes configured yet. Click <strong>+ Add Attribute Row</strong> or use Quick Add buttons above.</td></tr>';
            updateModalAttrCount();
        }
    }).catch(err => {
        document.getElementById('modalAttributesTableBody').innerHTML = `<tr><td colspan="5" class="text-danger small p-3">${err.message}</td></tr>`;
    });
}

function quickAddModalAttr(name, type, opts, req = 0) {
    const noRow = document.getElementById('modalNoAttrsRow'); if (noRow) noRow.remove();
    const tb = document.getElementById('modalAttributesTableBody');
    const ex = Array.from(tb.querySelectorAll('.ma-name')).find(i => i.value.trim().toLowerCase() === name.toLowerCase());
    if (ex) { ex.focus(); ex.classList.add('is-valid'); setTimeout(() => ex.classList.remove('is-valid'), 1000); return; }
    addModalAttributeRow({ attr_name: name, attr_type: type, attr_options: opts, is_required: req });
}

function addModalAttributeRow(attr = null) {
    const noRow = document.getElementById('modalNoAttrsRow'); if (noRow) noRow.remove();
    const tb = document.getElementById('modalAttributesTableBody');
    const a = attr || { attr_name: '', attr_type: 'SELECT', attr_options: '', is_required: 0 };
    const tr = document.createElement('tr'); tr.className = 'ma-row';
    tr.innerHTML = `
        <td><input type="text" class="form-control form-control-sm ma-name fw-semibold" value="${esc(a.attr_name)}" placeholder="e.g. Size, Fabric, Sleeve" required></td>
        <td>
            <select class="form-select form-select-sm ma-type">
                <option value="SELECT" selected>Dropdown</option>
            </select>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm ma-opts" value="${esc(a.attr_options || '')}" placeholder="e.g. S, M, L, XL or Red, Blue" required>
        </td>
        <td class="text-center">
            <div class="form-check form-switch d-inline-block">
                <input class="form-check-input ma-req" type="checkbox" ${a.is_required ? 'checked' : ''}>
            </div>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove(); updateModalAttrCount();" title="Remove"><i class="bi bi-trash"></i></button>
        </td>
    `;
    tb.appendChild(tr);
    updateModalAttrCount();
}

function handleMAType(sel) {
    // Dropdown is only type available
}

function updateModalAttrCount() {
    const count = document.querySelectorAll('#modalAttributesTableBody tr.ma-row').length;
    const badge = document.getElementById('modalAttrCount'); if (badge) badge.textContent = count;
}

function submitModalAttributes() {
    if (!modalAttrTarget || !modalAttrTarget.catId) return;
    const btn = document.getElementById('btnSaveModalAttrs');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const attrs = [];
    document.querySelectorAll('#modalAttributesTableBody tr.ma-row').forEach((r, i) => {
        const n = r.querySelector('.ma-name').value.trim(); if (!n) return;
        attrs.push({
            attr_name: n,
            attr_type: r.querySelector('.ma-type').value,
            attr_options: r.querySelector('.ma-opts').value.trim(),
            is_required: r.querySelector('.ma-req').checked ? 1 : 0,
            sort_order: i + 1
        });
    });

    const p = new URLSearchParams();
    p.append('_csrf_token', CSRF_TOKEN);
    p.append('category_id', modalAttrTarget.catId);
    if (modalAttrTarget.subId) p.append('subcategory_id', modalAttrTarget.subId);
    if (modalAttrTarget.sscId) p.append('sub_subcategory_id', modalAttrTarget.sscId);
    p.append('attributes', JSON.stringify(attrs));

    fetch(`${APP_URL}/api/warehouse/catalog/attributes/save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: p.toString()
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.innerHTML = orig;
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalConfigAttributes')).hide();
            showToast('Success', `Attributes saved for "${modalAttrTarget.path}"!`, 'success');
            reloadCategoryTree(modalAttrTarget.catId, modalAttrTarget.subId, modalAttrTarget.sscId);
        } else {
            alert(data.message || 'Error saving attributes');
        }
    })
    .catch(err => {
        btn.disabled = false; btn.innerHTML = orig;
        alert('Error: ' + err.message);
    });
}

// =========================================================================
// UNIFIED ADD CATEGORY HIERARCHY MODAL
// =========================================================================
function openAddCategoryModal() {
    document.getElementById('formUnifiedCategory').reset();
    const typeSelect = document.getElementById('unifiedCatType');
    if (typeSelect) typeSelect.value = 'NORMAL';
    document.getElementById('unifiedAttributesTableBody').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalUnifiedCategory')).show();
    setTimeout(() => { const i = document.getElementById('unifiedCatName'); if (i) i.focus(); }, 400);
}

function quickAddUnifiedAttr(name, type, opts, req = 0) {
    const tb = document.getElementById('unifiedAttributesTableBody');
    const ex = Array.from(tb.querySelectorAll('.ua-name')).find(i => i.value.trim().toLowerCase() === name.toLowerCase());
    if (ex) { ex.focus(); ex.classList.add('is-valid'); setTimeout(() => ex.classList.remove('is-valid'), 1000); return; }
    addUnifiedAttributeRow({ attr_name: name, attr_type: type, attr_options: opts, is_required: req });
}

function addUnifiedAttributeRow(attr = null) {
    const tb = document.getElementById('unifiedAttributesTableBody');
    const a = attr || { attr_name: '', attr_type: 'SELECT', attr_options: '', is_required: 0 };
    const tr = document.createElement('tr'); tr.className = 'ua-row';
    tr.innerHTML = `<td><input type="text" class="form-control form-control-sm ua-name fw-semibold" value="${esc(a.attr_name)}" placeholder="e.g. Size, Fabric, Collar" required></td>
    <td><select class="form-select form-select-sm ua-type">
        <option value="SELECT" selected>Dropdown</option>
    </select></td>
    <td><input type="text" class="form-control form-control-sm ua-opts" value="${esc(a.attr_options || '')}" placeholder="e.g. S, M, L, XL or Red, Blue" required></td>
    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ua-req" type="checkbox" ${a.is_required ? 'checked' : ''}></div></td>
    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
    tb.appendChild(tr);
}

function handleUAType(sel) {
    // Dropdown is only type available
}

function submitUnifiedCategoryForm(e) {
    e.preventDefault();
    const cat = document.getElementById('unifiedCatName').value.trim();
    if (!cat) { alert('Main Category Name is required.'); document.getElementById('unifiedCatName').focus(); return; }
    const attrs = [];
    document.querySelectorAll('#unifiedAttributesTableBody tr.ua-row').forEach((r, i) => {
        const n = r.querySelector('.ua-name').value.trim(); if (!n) return;
        attrs.push({ attr_name: n, attr_type: r.querySelector('.ua-type').value, attr_options: r.querySelector('.ua-opts').value.trim(), is_required: r.querySelector('.ua-req').checked ? 1 : 0, sort_order: i + 1 });
    });
    const btn = document.getElementById('btnSubmitUnifiedCat');
    const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    const p = new URLSearchParams();
    p.append('_csrf_token', CSRF_TOKEN);
    p.append('category_name', cat);
    p.append('category_type', document.getElementById('unifiedCatType') ? document.getElementById('unifiedCatType').value : 'NORMAL');
    p.append('subcategory_name', document.getElementById('unifiedSubcatName').value.trim());
    p.append('sub_subcategory_name', document.getElementById('unifiedSubSubcatName').value.trim());
    p.append('attributes', JSON.stringify(attrs));
    fetch(`${APP_URL}/api/warehouse/catalog/create-full-hierarchy`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            btn.disabled = false; btn.innerHTML = orig;
            if (data.success) { bootstrap.Modal.getInstance(document.getElementById('modalUnifiedCategory')).hide(); showToast('Success', `Hierarchy "${data.category_name}" saved!`, 'success'); reloadCategoryTree(data.category_id, data.subcategory_id, data.sub_subcategory_id); }
            else alert(data.message || 'Error saving');
        }).catch(err => { btn.disabled = false; btn.innerHTML = orig; alert('Error: ' + err.message); });
}

// =========================================================================
// STANDARD CRUD HANDLERS
// =========================================================================
function openEditCategoryModal(e, id, name, type = 'NORMAL') {
    if (e) e.stopPropagation();
    document.getElementById('modalCatId').value = id;
    document.getElementById('modalCatName').value = name;
    const catTypeEl = document.getElementById('modalCatType');
    if (catTypeEl) catTypeEl.value = (type || 'NORMAL').toUpperCase();
    document.getElementById('modalCategoryTitle').textContent = 'Edit Main Category';
    new bootstrap.Modal(document.getElementById('modalCategory')).show();
}

function submitCategoryForm(e) {
    e.preventDefault();
    const id = document.getElementById('modalCatId').value, name = document.getElementById('modalCatName').value.trim();
    if (!name) return;
    const type = document.getElementById('modalCatType') ? document.getElementById('modalCatType').value : 'NORMAL';
    const url = id ? `${APP_URL}/api/warehouse-categories/update/${id}` : `${APP_URL}/api/warehouse-categories/quick-add`;
    const p = new URLSearchParams();
    p.append('_csrf_token', CSRF_TOKEN);
    p.append('name', name);
    p.append('category_type', type);
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { bootstrap.Modal.getInstance(document.getElementById('modalCategory')).hide(); showToast('Success', id ? 'Category updated.' : 'Category added.', 'success'); reloadCategoryTree(id || data.id); }
            else alert(data.message || 'Error');
        });
}

function deleteCategory(e, id, name) {
    if (e) e.stopPropagation();
    if (!confirm(`Delete category "${name}" and all subcategories?`)) return;
    const p = new URLSearchParams(); p.append('_csrf_token', CSRF_TOKEN);
    fetch(`${APP_URL}/api/warehouse-categories/delete/${id}`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { showToast('Deleted', `Category "${name}" removed.`, 'info'); reloadCategoryTree(); }
            else alert(data.message || 'Error');
        });
}

function openAddSubcategoryModal(e, pid, pname) {
    if (e) e.stopPropagation();
    document.getElementById('modalSubcatParentId').value = pid;
    document.getElementById('modalSubcatParentName').value = pname;
    document.getElementById('modalSubcatId').value = '';
    document.getElementById('modalSubcatName').value = '';
    document.getElementById('modalSubcatTitle').textContent = `Add Subcategory under ${pname}`;
    new bootstrap.Modal(document.getElementById('modalSubcategory')).show();
}

function openEditSubcategoryModal(e, id, name, pid, pname) {
    if (e) e.stopPropagation();
    document.getElementById('modalSubcatParentId').value = pid;
    document.getElementById('modalSubcatParentName').value = pname;
    document.getElementById('modalSubcatId').value = id;
    document.getElementById('modalSubcatName').value = name;
    document.getElementById('modalSubcatTitle').textContent = `Edit Subcategory (${pname})`;
    new bootstrap.Modal(document.getElementById('modalSubcategory')).show();
}

function submitSubcategoryForm(e) {
    e.preventDefault();
    const id = document.getElementById('modalSubcatId').value, pid = document.getElementById('modalSubcatParentId').value, name = document.getElementById('modalSubcatName').value.trim();
    if (!name) return;
    const url = id ? `${APP_URL}/api/warehouse-subcategories/update/${id}` : `${APP_URL}/api/warehouse-subcategories/quick-add`;
    const p = new URLSearchParams(); p.append('_csrf_token', CSRF_TOKEN); p.append('category_id', pid); p.append('name', name);
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { bootstrap.Modal.getInstance(document.getElementById('modalSubcategory')).hide(); showToast('Success', id ? 'Subcategory updated.' : 'Subcategory added.', 'success'); reloadCategoryTree(pid, id || data.id); }
            else alert(data.message || 'Error');
        });
}

function deleteSubcategory(e, id, name) {
    if (e) e.stopPropagation();
    if (!confirm(`Delete subcategory "${name}"?`)) return;
    const p = new URLSearchParams(); p.append('_csrf_token', CSRF_TOKEN);
    fetch(`${APP_URL}/api/warehouse-subcategories/delete/${id}`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { showToast('Deleted', `Subcategory "${name}" removed.`, 'info'); reloadCategoryTree(selectedCatId); }
            else alert(data.message || 'Error');
        });
}

function openAddSubSubcategoryModal(e, pid, pname) {
    if (e) e.stopPropagation();
    document.getElementById('modalSubSubcatParentId').value = pid;
    document.getElementById('modalSubSubcatParentName').value = pname;
    document.getElementById('modalSubSubcatId').value = '';
    document.getElementById('modalSubSubcatName').value = '';
    document.getElementById('modalSubSubcatTitle').textContent = `Add Sub-Subcategory under ${pname}`;
    new bootstrap.Modal(document.getElementById('modalSubSubcategory')).show();
}

function openEditSubSubcategoryModal(e, id, name, pid, pname) {
    if (e) e.stopPropagation();
    document.getElementById('modalSubSubcatParentId').value = pid;
    document.getElementById('modalSubSubcatParentName').value = pname;
    document.getElementById('modalSubSubcatId').value = id;
    document.getElementById('modalSubSubcatName').value = name;
    document.getElementById('modalSubSubcatTitle').textContent = `Edit Sub-Subcategory (${pname})`;
    new bootstrap.Modal(document.getElementById('modalSubSubcategory')).show();
}

function submitSubSubcategoryForm(e) {
    e.preventDefault();
    const id = document.getElementById('modalSubSubcatId').value, pid = document.getElementById('modalSubSubcatParentId').value, name = document.getElementById('modalSubSubcatName').value.trim();
    if (!name) return;
    const url = id ? `${APP_URL}/api/warehouse-sub-subcategories/update/${id}` : `${APP_URL}/api/warehouse-sub-subcategories/quick-add`;
    const p = new URLSearchParams(); p.append('_csrf_token', CSRF_TOKEN); p.append('subcategory_id', pid); p.append('name', name);
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { bootstrap.Modal.getInstance(document.getElementById('modalSubSubcategory')).hide(); showToast('Success', id ? 'Sub-Subcategory updated.' : 'Sub-Subcategory added.', 'success'); reloadCategoryTree(selectedCatId, pid, id || data.id); }
            else alert(data.message || 'Error');
        });
}

function deleteSubSubcategory(e, id, name) {
    if (e) e.stopPropagation();
    if (!confirm(`Delete sub-subcategory "${name}"?`)) return;
    const p = new URLSearchParams(); p.append('_csrf_token', CSRF_TOKEN);
    fetch(`${APP_URL}/api/warehouse-sub-subcategories/delete/${id}`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: p.toString() })
        .then(r => r.json()).then(data => {
            if (data.success) { showToast('Deleted', `Sub-Subcategory "${name}" removed.`, 'info'); reloadCategoryTree(selectedCatId, selectedSubId); }
            else alert(data.message || 'Error');
        });
}

function showToast(title, message, type = 'info') {
    const d = document.createElement('div');
    d.className = `alert alert-${type} alert-dismissible fade show shadow-lg position-fixed bottom-0 end-0 m-4`;
    d.style.cssText = 'z-index:9999;min-width:280px;';
    d.innerHTML = `<strong>${title}:</strong> ${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(d);
    setTimeout(() => { d.classList.remove('show'); setTimeout(() => d.remove(), 250); }, 3500);
}
</script>

<!-- ========================================================================= -->
<!-- STYLES -->
<!-- ========================================================================= -->
<style>
/* Header badges & level icons */
.cat-level-num { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:700; color:#fff; flex-shrink:0; }
.item-icon-badge { width:32px; height:32px; border-radius:8px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; transition:all .15s; }
.item-icon-badge.active { background:#dbeafe; }
.item-icon-badge.active-info { background:#cffafe; }
.item-icon-badge.active-success { background:#dcfce7; }

/* Cascading column list items (Image 2 style) */
.col-list-item { cursor:pointer; padding:9px 12px; border-bottom:1px solid #f0f3f8; transition:all .12s; border-left:3px solid transparent; background:#fff; }
.col-list-item:hover { background:#f8fafc; border-left-color:#cbd5e1; }
.col-list-item:last-child { border-bottom:none; }
.col-list-item.active-col-item { background:#f0f6ff!important; border-left:3px solid #3b82f6!important; }
.col-list-item.active-col-item .item-title { color:#1d4ed8!important; }
.col-list-item.active-col-item-info { background:#f0faff!important; border-left:3px solid #06b6d4!important; }
.col-list-item.active-col-item-info .item-title { color:#0e7490!important; }
.col-list-item.active-col-item-success { background:#f0fdf6!important; border-left:3px solid #10b981!important; }
.col-list-item.active-col-item-success .item-title { color:#047857!important; }

/* Status & info pills (Image 1 style) */
.pill-active { display:inline-block; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:600; color:#16a34a; background:#f0fdf4; border:1px solid #bbf7d0; }
.pill-info { display:inline-block; padding:2px 7px; border-radius:20px; font-size:.68rem; font-weight:600; color:#0369a1; background:#f0f9ff; border:1px solid #bae6fd; }
.pill-attr { display:inline-block; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:600; color:#92400e; background:#fffbeb; border:1px solid #fde68a; }
.code-badge { display:inline-block; padding:1px 6px; border-radius:4px; font-size:.7rem; font-family:monospace; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; }

/* Action icon buttons (Image 1 style: clean, outlined, 28x28) */
.icon-btn { width:28px; height:28px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-size:.76rem; border-radius:6px; border:1px solid #d1d5db; background:#fff; cursor:pointer; transition:all .12s; color:#475569; }
.icon-btn:hover { background:#f1f5f9; border-color:#94a3b8; color:#0f172a; }
.icon-btn.danger { border-color:#fca5a5; color:#dc2626; }
.icon-btn.danger:hover { background:#fee2e2; border-color:#f87171; color:#b91c1c; }

/* Quick Add chips */
.btn-chip { padding:3px 9px; font-size:.72rem; border-radius:20px; border:1px solid #d1d5db; background:#fff; cursor:pointer; transition:all .12s; color:#374151; font-weight:500; }
.btn-chip:hover { background:#f3f4f6; border-color:#9ca3af; }

/* Inline attributes editor */
.attr-row { border-bottom:1px solid #f0f3f8; padding:5px 8px; background:#fff; }
.attr-row:hover { background:#fafbfc; }
.attr-row:last-child { border-bottom:none; }
.attr-row-inner { display:flex; align-items:center; gap:4px; flex-wrap:nowrap; }
.ar-name { flex:2; min-width:70px; }
.ar-type { flex:1.2; min-width:60px; }
.ar-opts { flex:2; min-width:60px; }
.ar-req { flex:0 0 36px; }
.ar-del { flex:0 0 28px; }
.attr-row input.form-control-sm, .attr-row select.form-select-sm { font-size:.73rem; padding:2px 5px; height:26px; }

/* Custom scrollbars */
#categoriesListContainer::-webkit-scrollbar, #subcategoriesListContainer::-webkit-scrollbar, #subSubcategoriesListContainer::-webkit-scrollbar, #attributesListContainer::-webkit-scrollbar { width:4px; }
#categoriesListContainer::-webkit-scrollbar-thumb, #subcategoriesListContainer::-webkit-scrollbar-thumb, #subSubcategoriesListContainer::-webkit-scrollbar-thumb, #attributesListContainer::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:2px; }

/* Table hover & expand styles */
.cat-table-row:hover { background-color:#f8fafc!important; }
.cat-table-row td { vertical-align:middle; }
</style>
