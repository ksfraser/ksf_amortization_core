/**
 * Frequency Cell Editor - Domain-specific cell editing for interest frequency table
 * 
 * SRP: Single responsibility of handling frequency cell edits and AJAX submission.
 * Extends CellEditor with frequency-specific AJAX handling via InterestFreqHandler.
 * 
 * Usage:
 *   const freqEditor = new FrequencyCellEditor('name-cell-freq-1');
 *   freqEditor.attach();
 */
class FrequencyCellEditor extends CellEditor {
    constructor(cellId, options = {}) {
        super(cellId, {
            onSave: (value, cellId, originalValue) => {
                // Extract frequency ID from cell ID or data attribute
                const freqId = this.extractFrequencyId(cellId);
                const fieldName = this.extractFieldName(cellId);
                
                if (!window.interestFreqHandler) {
                    alert('Error: Interest frequency handler not loaded');
                    return;
                }
                
                // Call handler to submit via AJAX
                window.interestFreqHandler.updateField(freqId, fieldName, value, originalValue, cellId);
            },
            onCancel: (cellId) => {
                console.log('Frequency cell edit cancelled:', cellId);
            },
            ...options
        });
    }
    
    /**
     * Extract frequency ID from cell ID or data attribute
     */
    extractFrequencyId(cellId) {
        // Try data attribute first
        if (this.cell && this.cell.getAttribute('data-freq-id')) {
            return this.cell.getAttribute('data-freq-id');
        }
        
        // Parse from cell ID pattern: "fieldname-cell-freq-1"
        const match = cellId.match(/freq-(\d+)$/);
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
        
        // Parse from cell ID: "name-cell-freq-1" -> "name"
        const match = cellId.match(/^(\w+)-cell/);
        return match ? match[1] : 'unknown';
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FrequencyCellEditor;
}
