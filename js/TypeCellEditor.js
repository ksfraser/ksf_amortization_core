/**
 * Loan Type Cell Editor - Domain-specific cell editing for loan type table
 * 
 * SRP: Single responsibility of handling loan type cell edits and AJAX submission.
 * Extends CellEditor with loan type-specific AJAX handling via LoanTypeHandler.
 * 
 * Usage:
 *   const typeEditor = new TypeCellEditor('name-cell-type-5');
 *   typeEditor.attach();
 */
class TypeCellEditor extends CellEditor {
    constructor(cellId, options = {}) {
        super(cellId, {
            onSave: (value, cellId, originalValue) => {
                // Extract type ID from cell ID or data attribute
                const typeId = this.extractTypeId(cellId);
                const fieldName = this.extractFieldName(cellId);
                
                if (!window.loanTypeHandler) {
                    alert('Error: Loan type handler not loaded');
                    return;
                }
                
                // Call handler to submit via AJAX
                window.loanTypeHandler.updateField(typeId, fieldName, value, originalValue, cellId);
            },
            onCancel: (cellId) => {
                console.log('Type cell edit cancelled:', cellId);
            },
            ...options
        });
    }
    
    /**
     * Extract type ID from cell ID or data attribute
     */
    extractTypeId(cellId) {
        // Try data attribute first
        if (this.cell && this.cell.getAttribute('data-type-id')) {
            return this.cell.getAttribute('data-type-id');
        }
        
        // Parse from cell ID pattern: "fieldname-cell-type-5"
        const match = cellId.match(/type-(\d+)$/);
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
        
        // Parse from cell ID: "name-cell-type-5" -> "name"
        const match = cellId.match(/^(\w+)-cell/);
        return match ? match[1] : 'unknown';
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TypeCellEditor;
}
