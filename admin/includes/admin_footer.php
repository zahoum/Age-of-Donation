<!-- Admin Footer -->
    </div> <!-- Close main-content -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Global functions
        function confirmAction(message) {
            return confirm(message || 'هل أنت متأكد من تنفيذ هذا الإجراء؟');
        }
        
        function showToast(message, type = 'success') {
            // Create toast container if not exists
            if (!$('#toast-container').length) {
                $('body').append('<div id="toast-container" style="position: fixed; top: 20px; left: 20px; z-index: 9999;"></div>');
            }
            
            const bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#17a2b8');
            const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
            
            const toast = $(`
                <div class="toast align-items-center text-white border-0 mb-2" role="alert" style="background: ${bgColor}; min-width: 250px;" data-bs-autohide="true" data-bs-delay="3000">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas ${icon} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);
            
            $('#toast-container').append(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }
        
        // Add fade-in animation to cards
        $(document).ready(function() {
            $('.card, .stat-card').hide().fadeIn(500);
        });
    </script>
</body>
</html>