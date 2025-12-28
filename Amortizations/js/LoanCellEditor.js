/**
 * Loan Cell Editor - Domain-specific cell editing for loan summary table
 * 
 * SRP: Single responsibility of handling loan cell edits and AJAX submission.
 * Extends CellEditor with loan-specific AJAX handling via LoanHandler.
 * 
 * Usage:
 *   const loanEditor = new LoanCellEditor('borrower-cell-loan-123');
 *   loanEditor.attach();
 */
class LoanCellEditor extends CellEditor {
    constructor(cellId, options = {}) {
        super(cellId, {
            onSave: (value, cellId, originalValue) => {
                // Extract loan ID from cell ID or data attribute
                const loanId = this.extractLoanId(cellId);
                const fieldName = this.extractFieldName(cellId);
                
                if (!window.loanHandler) {
                    alert('Error: Loan handler not loaded');
                    return;
                }
                
                // Call loan handler to submit via AJAX
                window.loanHandler.updateField(loanId, fieldName, value, originalValue, cellId);
            },
            onCancel: (cellId) => {
                console.log('Loan cell edit cancelled:', cellId);
            },
            ...options
        });
    }
    
    /**
     * Extract loan ID from cell ID or data attribute
     */
    extractLoanId(cellId) {
        // Try data attribute first
        if (this.cell && this.cell.getAttribute('data-loan-id')) {
            return this.cell.getAttribute('data-loan-id');
        }
        
        // Parse from cell ID pattern: "fieldname-cell-loan-123"
        const match = cellId.match(/loan-(\d+)$/);
        return match ? match[1] : null;
    }
    
    /**
     * Extract field name from cell ID or data attribute
     */
    extractFieldName(cellId) {
        // Try data attribute first
        if (this.cell && this.cell.getAttribute('data-field')) {
            return this.cell.getAttribute('data-field');
        }
        
        // Parse from cell ID: "borrower-cell-loan-123" -> "borrower"
        const match = cellId.match(/^(\w+)-cell/);
        return match ? match[1] : 'unknown';
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LoanCellEditor;
}
