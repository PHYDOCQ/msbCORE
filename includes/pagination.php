<?php

class Pagination {
    private $currentPage;
    private $totalRecords;
    private $recordsPerPage;
    private $totalPages;
    private $baseUrl;
    private $queryParams;
    
    public function __construct($currentPage, $totalRecords, $recordsPerPage = RECORDS_PER_PAGE, $baseUrl = '', $queryParams = []) {
        $this->currentPage = max(1, (int)$currentPage);
        $this->totalRecords = (int)$totalRecords;
        $this->recordsPerPage = max(1, (int)$recordsPerPage);
        $this->totalPages = ceil($this->totalRecords / $this->recordsPerPage);
        $this->baseUrl = $baseUrl ?: $_SERVER['PHP_SELF'];
        $this->queryParams = $queryParams;
        
        // Ensure current page is within valid range
        if ($this->currentPage > $this->totalPages && $this->totalPages > 0) {
            $this->currentPage = $this->totalPages;
        }
    }
    
    public function getOffset() {
        return ($this->currentPage - 1) * $this->recordsPerPage;
    }
    
    public function getLimit() {
        return $this->recordsPerPage;
    }
    
    public function getCurrentPage() {
        return $this->currentPage;
    }
    
    public function getTotalPages() {
        return $this->totalPages;
    }
    
    public function getTotalRecords() {
        return $this->totalRecords;
    }
    
    public function hasPages() {
        return $this->totalPages > 1;
    }
    
    public function hasPrevious() {
        return $this->currentPage > 1;
    }
    
    public function hasNext() {
        return $this->currentPage < $this->totalPages;
    }
    
    public function getPreviousPage() {
        return $this->hasPrevious() ? $this->currentPage - 1 : 1;
    }
    
    public function getNextPage() {
        return $this->hasNext() ? $this->currentPage + 1 : $this->totalPages;
    }
    
    private function buildUrl($page, $params = []) {
        $allParams = array_merge($this->queryParams, $params, ['page' => $page]);
        return $this->baseUrl . '?' . http_build_query($allParams);
    }
    
    public function getInfo() {
        $start = $this->getOffset() + 1;
        $end = min($this->getOffset() + $this->recordsPerPage, $this->totalRecords);
        
        return sprintf(
            'Showing %d to %d of %d entries',
            $start,
            $end,
            $this->totalRecords
        );
    }
    
    public function render($options = []) {
        if (!$this->hasPages()) {
            return '';
        }
        
        $maxLinks = $options['max_links'] ?? MAX_PAGINATION_LINKS;
        $showInfo = $options['show_info'] ?? true;
        $showFirst = $options['show_first'] ?? true;
        $showLast = $options['show_last'] ?? true;
        $size = $options['size'] ?? 'normal'; // 'small', 'normal', 'large'
        $alignment = $options['alignment'] ?? 'center'; // 'start', 'center', 'end'
        
        $sizeClass = $size === 'small' ? 'pagination-sm' : ($size === 'large' ? 'pagination-lg' : '');
        $alignmentClass = $alignment === 'start' ? 'justify-content-start' : 
                         ($alignment === 'end' ? 'justify-content-end' : 'justify-content-center');
        
        $html = '<nav aria-label="Page navigation">';
        
        // Show info if requested
        if ($showInfo) {
            $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
            $html .= '<span class="text-muted">' . $this->getInfo() . '</span>';
            $html .= '<div>';
        }
        
        $html .= '<ul class="pagination mb-0 ' . $sizeClass . ' ' . $alignmentClass . '">';
        
        // Previous button
        if ($this->hasPrevious()) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->buildUrl($this->getPreviousPage()) . '" aria-label="Previous">';
            $html .= '<span aria-hidden="true">&laquo;</span>';
            $html .= '</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&laquo;</span>';
            $html .= '</li>';
        }
        
        // First page link
        if ($showFirst && $this->currentPage > ($maxLinks / 2) + 1 && $this->totalPages > $maxLinks) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->buildUrl(1) . '">1</a>';
            $html .= '</li>';
            
            if ($this->currentPage > ($maxLinks / 2) + 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Calculate start and end page numbers
        $start = max(1, $this->currentPage - floor($maxLinks / 2));
        $end = min($this->totalPages, $start + $maxLinks - 1);
        
        // Adjust start if end is at the boundary
        if ($end - $start < $maxLinks - 1) {
            $start = max(1, $end - $maxLinks + 1);
        }
        
        // Page number links
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $this->currentPage) {
                $html .= '<li class="page-item active" aria-current="page">';
                $html .= '<span class="page-link">' . $i . '</span>';
                $html .= '</li>';
            } else {
                $html .= '<li class="page-item">';
                $html .= '<a class="page-link" href="' . $this->buildUrl($i) . '">' . $i . '</a>';
                $html .= '</li>';
            }
        }
        
        // Last page link
        if ($showLast && $this->currentPage < $this->totalPages - ($maxLinks / 2) && $this->totalPages > $maxLinks) {
            if ($this->currentPage < $this->totalPages - ($maxLinks / 2) - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->buildUrl($this->totalPages) . '">' . $this->totalPages . '</a>';
            $html .= '</li>';
        }
        
        // Next button
        if ($this->hasNext()) {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . $this->buildUrl($this->getNextPage()) . '" aria-label="Next">';
            $html .= '<span aria-hidden="true">&raquo;</span>';
            $html .= '</a>';
            $html .= '</li>';
        } else {
            $html .= '<li class="page-item disabled">';
            $html .= '<span class="page-link">&raquo;</span>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        if ($showInfo) {
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</nav>';
        
        return $html;
    }
    
    public function getPageSizeSelector($currentSize = null, $options = [10, 20, 50, 100]) {
        $currentSize = $currentSize ?? $this->recordsPerPage;
        
        $html = '<div class="d-flex align-items-center">';
        $html .= '<label for="page-size" class="form-label me-2 mb-0">Show:</label>';
        $html .= '<select class="form-select form-select-sm" style="width: auto;" id="page-size" onchange="changePageSize(this.value)">';
        
        foreach ($options as $size) {
            $selected = $size == $currentSize ? 'selected' : '';
            $html .= "<option value=\"{$size}\" {$selected}>{$size}</option>";
        }
        
        $html .= '</select>';
        $html .= '<span class="ms-2">entries</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    public function toArray() {
        return [
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'total_records' => $this->totalRecords,
            'records_per_page' => $this->recordsPerPage,
            'offset' => $this->getOffset(),
            'has_previous' => $this->hasPrevious(),
            'has_next' => $this->hasNext(),
            'info' => $this->getInfo()
        ];
    }
    
    public function toJson() {
        return json_encode($this->toArray());
    }
}

// Helper function for creating pagination
function paginate($currentPage, $totalRecords, $recordsPerPage = RECORDS_PER_PAGE, $baseUrl = '', $queryParams = []) {
    return new Pagination($currentPage, $totalRecords, $recordsPerPage, $baseUrl, $queryParams);
}
?>

<script>
function changePageSize(size) {
    const url = new URL(window.location);
    url.searchParams.set('limit', size);
    url.searchParams.set('page', 1); // Reset to first page
    window.location.href = url.toString();
}
</script>
