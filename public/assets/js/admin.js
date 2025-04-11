/**
 * Admin JavaScript file
 * Optimized for performance and maintainability
 */

'use strict';

// Admin namespace to avoid global scope pollution
const Admin = (function() {
    // Private variables
    const cacheDOM = {};
    let settings = {
        dataTableOptions: {
            responsive: true,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Vietnamese.json"
            },
            pageLength: 25,
            ordering: true
        },
        swalOptions: {
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Xác nhận',
            cancelButtonText: 'Hủy'
        }
    };

    // Cache DOM elements
    const cacheDOMElements = () => {
        cacheDOM.tables = document.querySelectorAll('.data-table');
        cacheDOM.deleteButtons = document.querySelectorAll('.btn-delete');
        cacheDOM.statusToggleButtons = document.querySelectorAll('.status-toggle');
        cacheDOM.featuredToggleButtons = document.querySelectorAll('.featured-toggle');
        cacheDOM.hotToggleButtons = document.querySelectorAll('.hot-toggle');
        cacheDOM.completedToggleButtons = document.querySelectorAll('.completed-toggle');
        cacheDOM.imageUploadInputs = document.querySelectorAll('.image-upload');
        cacheDOM.summernoteTriggers = document.querySelectorAll('.summernote');
        cacheDOM.slugGeneratorInputs = document.querySelectorAll('.slug-generator');
        cacheDOM.slugTargetInputs = document.querySelectorAll('.slug-target');
    };

    // Initialize DataTables
    const initDataTables = () => {
        if (cacheDOM.tables && cacheDOM.tables.length > 0) {
            cacheDOM.tables.forEach(table => {
                const tableId = table.id;
                const customOptions = table.dataset.options ? JSON.parse(table.dataset.options) : {};
                const options = { ...settings.dataTableOptions, ...customOptions };

                try {
                    if ($.fn.DataTable) {
                        $(table).DataTable(options);
                    }
                } catch (error) {
                    console.error(`Error initializing DataTable for ${tableId}:`, error);
                }
            });
        }
    };

    // Initialize Summernote editors
    const initSummernote = () => {
        if (cacheDOM.summernoteTriggers && cacheDOM.summernoteTriggers.length > 0 && $.fn.summernote) {
            cacheDOM.summernoteTriggers.forEach(editor => {
                try {
                    $(editor).summernote({
                        height: 300,
                        toolbar: [
                            ['style', ['style']],
                            ['font', ['bold', 'underline', 'clear']],
                            ['color', ['color']],
                            ['para', ['ul', 'ol', 'paragraph']],
                            ['table', ['table']],
                            ['insert', ['link', 'picture', 'video']],
                            ['view', ['fullscreen', 'codeview', 'help']]
                        ],
                        callbacks: {
                            onImageUpload: function(files) {
                                for (let i = 0; i < files.length; i++) {
                                    uploadImageForEditor(files[i], editor);
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error initializing Summernote editor:', error);
                }
            });
        }
    };

    // Upload image for Summernote editor
    const uploadImageForEditor = (file, editor) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append(csrf_token_name, csrf_hash);

        $.ajax({
            url: `${baseUrl}/admin/uploads/upload-image`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $(editor).summernote('insertImage', response.url);
                } else {
                    alert('Upload failed: ' + response.message);
                }
            },
            error: function() {
                alert('Error occurred during upload');
            }
        });
    };

    // Initialize image preview for file inputs
    const initImagePreview = () => {
        if (cacheDOM.imageUploadInputs && cacheDOM.imageUploadInputs.length > 0) {
            cacheDOM.imageUploadInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const previewId = this.dataset.preview;
                    if (previewId) {
                        const preview = document.getElementById(previewId);
                        if (preview && this.files && this.files[0]) {
                            const reader = new FileReader();

                            reader.onload = function(e) {
                                preview.src = e.target.result;
                                preview.classList.remove('d-none');
                            };

                            reader.readAsDataURL(this.files[0]);
                        }
                    }
                });
            });
        }
    };

    // Initialize delete confirmation
    const initDeleteConfirmation = () => {
        if (cacheDOM.deleteButtons && cacheDOM.deleteButtons.length > 0) {
            cacheDOM.deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    const id = this.dataset.id;
                    const title = this.dataset.title || 'this item';
                    const url = this.dataset.url || this.getAttribute('href');

                    if (!id && !url) {
                        console.error('Delete button missing id or url attribute');
                        return;
                    }

                    // Use SweetAlert if available, otherwise use confirm
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Xác nhận xóa',
                            text: `Bạn có chắc chắn muốn xóa "${title}"?`,
                            icon: 'warning',
                            showCancelButton: true,
                            ...settings.swalOptions
                        }).then((result) => {
                            if (result.isConfirmed) {
                                processDelete(id, url);
                            }
                        });
                    } else {
                        if (confirm(`Bạn có chắc chắn muốn xóa "${title}"?`)) {
                            processDelete(id, url);
                        }
                    }
                });
            });
        }
    };

    // Process delete action
    const processDelete = (id, url) => {
        // If it's a direct URL, navigate to it
        if (url && !id) {
            window.location.href = url;
            return;
        }

        // If we have an ID, assume it's an AJAX delete
        if (id) {
            $.ajax({
                url: url || `${baseUrl}/admin/ajax-delete`,
                method: 'POST',
                data: {
                    id: id,
                    [csrf_token_name]: csrf_hash
                },
                success: function(response) {
                    if (response.success) {
                        // If SweetAlert is available, use it
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Thành công!',
                                text: response.message || 'Đã xóa thành công.',
                                icon: 'success',
                                confirmButtonColor: settings.swalOptions.confirmButtonColor
                            }).then(() => {
                                reloadOrRemoveRow(id);
                            });
                        } else {
                            alert(response.message || 'Đã xóa thành công.');
                            reloadOrRemoveRow(id);
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Lỗi!',
                                text: response.message || 'Đã xảy ra lỗi khi xóa.',
                                icon: 'error',
                                confirmButtonColor: settings.swalOptions.confirmButtonColor
                            });
                        } else {
                            alert(response.message || 'Đã xảy ra lỗi khi xóa.');
                        }
                    }
                },
                error: function() {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Lỗi!',
                            text: 'Đã xảy ra lỗi khi kết nối đến máy chủ.',
                            icon: 'error',
                            confirmButtonColor: settings.swalOptions.confirmButtonColor
                        });
                    } else {
                        alert('Đã xảy ra lỗi khi kết nối đến máy chủ.');
                    }
                }
            });
        }
    };

    // Handle row removal or page reload after delete
    const reloadOrRemoveRow = (id) => {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (row) {
            // If using DataTable, use API to remove row
            const table = $(row).closest('table.dataTable');
            if (table.length && $.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
                const dt = table.DataTable();
                dt.row(row).remove().draw();
            } else {
                // Otherwise, just remove the row from DOM
                row.remove();
            }
        } else {
            // If no row found, reload the page
            window.location.reload();
        }
    };

    // Initialize status toggle switches
    const initStatusToggles = () => {
        const setupToggle = (buttons, endpoint, field) => {
            if (buttons && buttons.length > 0) {
                buttons.forEach(button => {
                    button.addEventListener('change', function() {
                        const id = this.dataset.id;
                        const value = this.checked ? 1 : 0;

                        $.ajax({
                            url: `${baseUrl}/admin/${endpoint}/update-status`,
                            method: 'POST',
                            data: {
                                id: id,
                                [field]: value,
                                [csrf_token_name]: csrf_hash
                            },
                            success: function(response) {
                                if (!response.success) {
                                    console.error('Error updating status:', response.message);
                                    // Revert the toggle if failed
                                    button.checked = !button.checked;
                                }
                            },
                            error: function() {
                                console.error('Server error updating status');
                                // Revert the toggle on error
                                button.checked = !button.checked;
                            }
                        });
                    });
                });
            }
        };

        // Setup different types of toggle switches
        setupToggle(cacheDOM.statusToggleButtons, 'stories', 'status');
        setupToggle(cacheDOM.featuredToggleButtons, 'stories', 'is_featured');
        setupToggle(cacheDOM.hotToggleButtons, 'stories', 'is_hot');
        setupToggle(cacheDOM.completedToggleButtons, 'stories', 'is_completed');
    };

    // Initialize slug generator
    const initSlugGenerator = () => {
        if (cacheDOM.slugGeneratorInputs && cacheDOM.slugGeneratorInputs.length > 0) {
            cacheDOM.slugGeneratorInputs.forEach((input, index) => {
                const targetInput = cacheDOM.slugTargetInputs[index];
                if (targetInput) {
                    input.addEventListener('blur', function() {
                        if (targetInput.value === '') {
                            const title = this.value;
                            if (title) {
                                generateSlug(title, targetInput);
                            }
                        }
                    });
                }
            });
        }
    };

    // Generate slug from title
    const generateSlug = (title, targetInput) => {
        $.ajax({
            url: `${baseUrl}/admin/stories/generate-slug`,
            method: 'POST',
            data: {
                title: title,
                [csrf_token_name]: csrf_hash
            },
            success: function(response) {
                if (response.success) {
                    targetInput.value = response.slug;
                }
            }
        });
    };

    // Public API
    return {
        init() {
            cacheDOMElements();
            initDataTables();
            initSummernote();
            initImagePreview();
            initDeleteConfirmation();
            initStatusToggles();
            initSlugGenerator();

            // Return for chaining
            return this;
        },

        // Allow updating settings from outside
        updateSettings(newSettings) {
            settings = { ...settings, ...newSettings };
            return this;
        },

        // Expose methods that might need to be called individually
        refreshDataTables() {
            initDataTables();
            return this;
        }
    };
})();

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize admin features
    Admin.init();

    // Additional initialization code here
});
