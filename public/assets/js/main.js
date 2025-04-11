document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Search functionality
    initSearchFunctionality();

    // Chapter navigation shortcuts (arrow keys)
    initChapterNavigation();

    // Reading settings (font size, theme)
    initReadingSettings();

    // Bookmark functionality
    initBookmarkFunctionality();

    // Lazy load images
    initLazyLoading();
});

/**
 * Initialize search suggestions functionality
 */
function initSearchFunctionality() {
    const searchInput = document.getElementById('search-input');
    const searchSuggestions = document.getElementById('search-suggestions');

    if (!searchInput || !searchSuggestions) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchSuggestions.innerHTML = '';
            searchSuggestions.classList.remove('show');
            return;
        }

        searchTimeout = setTimeout(function() {
            fetch(`${baseUrl}/api/search/suggestions?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchSuggestions.innerHTML = '';

                    if (data.suggestions && data.suggestions.length > 0) {
                        data.suggestions.forEach(suggestion => {
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.innerHTML = `
                                <img src="${suggestion.cover_image}" alt="${suggestion.title}" class="suggestion-img">
                                <div>
                                    <div class="suggestion-title">${suggestion.title}</div>
                                </div>
                            `;
                            item.addEventListener('click', function() {
                                window.location.href = `${baseUrl}/novel/${suggestion.slug}`;
                            });
                            searchSuggestions.appendChild(item);
                        });
                        searchSuggestions.classList.add('show');
                    } else {
                        searchSuggestions.classList.remove('show');
                    }
                })
                .catch(error => {
                    console.error('Error fetching search suggestions:', error);
                });
        }, 300);
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.classList.remove('show');
        }
    });
}

/**
 * Initialize chapter navigation with keyboard shortcuts
 */
function initChapterNavigation() {
    // Only initialize on chapter reading page
    if (!document.querySelector('.chapter-navigation')) return;

    const prevChapterLink = document.querySelector('.prev-chapter');
    const nextChapterLink = document.querySelector('.next-chapter');
    const novelLink = document.querySelector('.back-to-novel');

    document.addEventListener('keydown', function(e) {
        // Left arrow key for previous chapter
        if (e.keyCode === 37 && prevChapterLink) {
            window.location.href = prevChapterLink.href;
        }

        // Right arrow key for next chapter
        if (e.keyCode === 39 && nextChapterLink) {
            window.location.href = nextChapterLink.href;
        }

        // Escape key to go back to novel page
        if (e.keyCode === 27 && novelLink) {
            window.location.href = novelLink.href;
        }
    });
}

/**
 * Initialize reading settings functionality
 */
function initReadingSettings() {
    const readingSettingsToggle = document.getElementById('reading-settings-toggle');
    if (!readingSettingsToggle) return;

    const fontSizeSmall = document.getElementById('font-size-small');
    const fontSizeMedium = document.getElementById('font-size-medium');
    const fontSizeLarge = document.getElementById('font-size-large');
    const themeDark = document.getElementById('theme-dark');
    const themeLight = document.getElementById('theme-light');
    const themeSepia = document.getElementById('theme-sepia');
    const chapterContent = document.querySelector('.chapter-content');

    // Load saved settings
    loadReadingSettings();

    // Font size controls
    if (fontSizeSmall) {
        fontSizeSmall.addEventListener('click', function() {
            chapterContent.style.fontSize = '0.9rem';
            saveReadingSettings('fontSize', 'small');
        });
    }

    if (fontSizeMedium) {
        fontSizeMedium.addEventListener('click', function() {
            chapterContent.style.fontSize = '1.1rem';
            saveReadingSettings('fontSize', 'medium');
        });
    }

    if (fontSizeLarge) {
        fontSizeLarge.addEventListener('click', function() {
            chapterContent.style.fontSize = '1.3rem';
            saveReadingSettings('fontSize', 'large');
        });
    }

    // Theme controls
    if (themeDark) {
        themeDark.addEventListener('click', function() {
            document.body.classList.remove('theme-light', 'theme-sepia');
            document.body.classList.add('theme-dark');
            saveReadingSettings('theme', 'dark');
        });
    }

    if (themeLight) {
        themeLight.addEventListener('click', function() {
            document.body.classList.remove('theme-dark', 'theme-sepia');
            document.body.classList.add('theme-light');
            saveReadingSettings('theme', 'light');
        });
    }

    if (themeSepia) {
        themeSepia.addEventListener('click', function() {
            document.body.classList.remove('theme-dark', 'theme-light');
            document.body.classList.add('theme-sepia');
            saveReadingSettings('theme', 'sepia');
        });
    }
}

/**
 * Save reading settings to localStorage
 */
function saveReadingSettings(key, value) {
    const settings = JSON.parse(localStorage.getItem('readingSettings') || '{}');
    settings[key] = value;
    localStorage.setItem('readingSettings', JSON.stringify(settings));
}

/**
 * Load reading settings from localStorage
 */
function loadReadingSettings() {
    const settings = JSON.parse(localStorage.getItem('readingSettings') || '{}');
    const chapterContent = document.querySelector('.chapter-content');

    if (!chapterContent) return;

    // Apply font size
    if (settings.fontSize) {
        switch (settings.fontSize) {
            case 'small':
                chapterContent.style.fontSize = '0.9rem';
                break;
            case 'medium':
                chapterContent.style.fontSize = '1.1rem';
                break;
            case 'large':
                chapterContent.style.fontSize = '1.3rem';
                break;
        }
    }

    // Apply theme
    if (settings.theme) {
        document.body.classList.remove('theme-dark', 'theme-light', 'theme-sepia');
        document.body.classList.add(`theme-${settings.theme}`);
    }
}

/**
 * Initialize bookmark functionality
 */
function initBookmarkFunctionality() {
    const bookmarkBtn = document.getElementById('bookmark-btn');
    if (!bookmarkBtn) return;

    const novelId = bookmarkBtn.dataset.novelId;

    bookmarkBtn.addEventListener('click', function() {
        if (!userId) {
            // Redirect to login if not logged in
            window.location.href = `${baseUrl}/login?redirect=${encodeURIComponent(window.location.href)}`;
            return;
        }

        fetch(`${baseUrl}/api/bookmark/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ novel_id: novelId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.bookmarked) {
                    bookmarkBtn.innerHTML = '<i class="fas fa-bookmark"></i> Đã Lưu';
                    bookmarkBtn.classList.remove('btn-outline-primary');
                    bookmarkBtn.classList.add('btn-primary');
                } else {
                    bookmarkBtn.innerHTML = '<i class="far fa-bookmark"></i> Lưu Truyện';
                    bookmarkBtn.classList.remove('btn-primary');
                    bookmarkBtn.classList.add('btn-outline-primary');
                }
            }
        })
        .catch(error => {
            console.error('Error toggling bookmark:', error);
        });
    });
}

/**
 * Initialize lazy loading for images
 */
function initLazyLoading() {
    const lazyImages = [].slice.call(document.querySelectorAll('img.lazy'));

    if ('IntersectionObserver' in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    if (lazyImage.dataset.srcset) {
                        lazyImage.srcset = lazyImage.dataset.srcset;
                    }
                    lazyImage.classList.remove('lazy');
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });

        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        let active = false;

        const lazyLoad = function() {
            if (active === false) {
                active = true;

                setTimeout(function() {
                    lazyImages.forEach(function(lazyImage) {
                        if ((lazyImage.getBoundingClientRect().top <= window.innerHeight && lazyImage.getBoundingClientRect().bottom >= 0) && getComputedStyle(lazyImage).display !== 'none') {
                            lazyImage.src = lazyImage.dataset.src;
                            if (lazyImage.dataset.srcset) {
                                lazyImage.srcset = lazyImage.dataset.srcset;
                            }
                            lazyImage.classList.remove('lazy');

                            lazyImages = lazyImages.filter(function(image) {
                                return image !== lazyImage;
                            });

                            if (lazyImages.length === 0) {
                                document.removeEventListener('scroll', lazyLoad);
                                window.removeEventListener('resize', lazyLoad);
                                window.removeEventListener('orientationchange', lazyLoad);
                            }
                        }
                    });

                    active = false;
                }, 200);
            }
        };

        document.addEventListener('scroll', lazyLoad);
        window.addEventListener('resize', lazyLoad);
        window.addEventListener('orientationchange', lazyLoad);
    }
}

/**
 * Initialize pagination for category, search, and tag pages
 */
function initPagination() {
    const paginationContainer = document.querySelector('.pagination-container');
    if (!paginationContainer) return;

    const currentUrl = new URL(window.location.href);
    const currentPage = parseInt(currentUrl.searchParams.get('page') || '1');
    const totalPages = parseInt(paginationContainer.dataset.totalPages || '1');

    let paginationHtml = '';

    // Previous button
    paginationHtml += `
        <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="${getPageUrl(currentPage - 1)}" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
    `;

    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="${getPageUrl(1)}">1</a></li>`;
        if (startPage > 2) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        paginationHtml += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="${getPageUrl(i)}">${i}</a>
            </li>
        `;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHtml += `<li class="page-item"><a class="page-link" href="${getPageUrl(totalPages)}">${totalPages}</a></li>`;
    }

    // Next button
    paginationHtml += `
        <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="${getPageUrl(currentPage + 1)}" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    `;

    paginationContainer.innerHTML = `<ul class="pagination">${paginationHtml}</ul>`;
}

/**
 * Helper function to get pagination URL
 */
function getPageUrl(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    return url.toString();
}

// Call pagination initialization
initPagination();
