jQuery(document).ready(function($) {
    // Biến lưu trạng thái hiện tại
    let currentPage = 1;
    let selectedParentId = 0;
    let selectedChildId = 0;
    let searchKeyword = '';
    let searchTimeout = null;
    console.log(ajax_object.current_language);
    // Hàm lấy danh mục cha
    function getParentCategories() {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_child_categories',
                parent_id: 0
            },
            success: function(response) {
                if (response.success) {
                    updateParentSelect(response.data);
                }
            }
        });
    }

    // Hàm cập nhật select danh mục cha
    function updateParentSelect(categories) {
        const $parentSelect = $('.idea-lvl1 select');
        $parentSelect.empty();
        if (ajax_object.current_language == 'vi') {
            $parentSelect.append('<option value="">Chọn chuyên đề</option>');
        } else if (ajax_object.current_language == 'en') {
            $parentSelect.append('<option value="">Select topic</option>');
        }
        
        categories.forEach(function(category) {
            $parentSelect.append(`<option value="${category.id}">${category.name}</option>`);
        });
    }

    // Hàm lấy danh mục con
    function getChildCategories(parentId) {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_child_categories',
                parent_id: parentId
            },
            success: function(response) {
                if (response.success) {
                    updateChildSelect(response.data);
                }
            }
        });
    }

    // Hàm cập nhật select danh mục con
    function updateChildSelect(categories) {
        const $childSelect = $('.idea-lvl2 select');
        $childSelect.empty();
        if (ajax_object.current_language == 'vi') {
            $childSelect.append('<option value="">Chọn danh mục</option>');
        } else if (ajax_object.current_language == 'en') {
            $childSelect.append('<option value="">Select category</option>');
        }
        
        if (categories && categories.length > 0) {
            categories.forEach(function(category) {
                $childSelect.append(`<option value="${category.id}">${category.name}</option>`);
            });
        }
    }

    // Hàm lọc bài viết
    function filterPosts() {
        let categoryId = null;
        
        // Nếu có chọn danh mục con
        if (selectedChildId) {
            categoryId = selectedChildId;
        } 
        // Nếu không có chọn danh mục con nhưng có chọn danh mục cha
        else if (selectedParentId) {
            categoryId = selectedParentId;
        }

        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'filter_idea_posts',
                category_id: categoryId,
                page: currentPage,
                search: searchKeyword
            },
            beforeSend: function() {
                const $container = $('.inner-list-idea');
                // Xóa tất cả các phần tử item-idea hiện có
                $container.find('.item-idea').remove();
                $container.empty();
                $container.addClass('loading');
            },
            success: function(response) {
                if (response.success) {
                    updatePostsList(response.data);
                }
            },
            complete: function() {
                $('.inner-list-idea').removeClass('loading');
            }
        });
    }

    // Hàm cập nhật danh sách bài viết
    function updatePostsList(data) {
        const $container = $('.inner-list-idea');
        
        if (data.posts.length === 0) {
            if (ajax_object.current_language == 'vi') {
                $container.html('<div class="no-posts">Không tìm thấy ý tưởng nào</div>');
            } else if (ajax_object.current_language == 'en') {
                $container.html('<div class="no-posts">No ideas found</div>');
            }
            $('.pagination-cntt').empty();
            return;
        }

        data.posts.forEach(function(post) {
            const postHtml = `
                <div class="item-idea">
                    <div class="image-idea">
                        <a href="${post.permalink}" class="_5oau block">
                            <img src="${post.image}" class="attachment-full size-full wp-post-image" decoding="async">
                        </a>
                    </div>
                    <div class="text-box-idea">
                        <div class="_3dqv">
                            <a href="${post.permalink}" class="_4wxo textLine-2">${post.title}</a>
                        </div>
                        <div class="_4nmk textLine-2">
                            ${post.excerpt}
                        </div>
                    </div>
                </div>
            `;
            $container.append(postHtml);
        });

        // Cập nhật phân trang
        updatePagination(data.max_num_pages);
    }

    // Hàm cập nhật phân trang
    function updatePagination(maxPages) {
        const $pagination = $('.pagination-cntt');
        $pagination.empty();

        if (maxPages <= 1) return;

        let paginationHTML = '';

        if (currentPage > 1) {
            paginationHTML += `<button data-page="${currentPage - 1}" class="pagination-feature-btn text-btn"><i class="fa-light fa-chevron-left"></i></button>`;
        }

        paginationHTML += `<button data-page="1" class="pagination-feature-btn ${currentPage === 1 ? "active" : ""}">1</button>`;

        if (currentPage > 3) {
            paginationHTML += `<span class="pagination-ellipsis pagination-feature-btn">...</span>`;
        }

        let start = Math.max(2, currentPage - 1);
        let end = Math.min(maxPages - 1, currentPage + 1);
        for (let i = start; i <= end; i++) {
            paginationHTML += `<button data-page="${i}" class="pagination-feature-btn ${i === currentPage ? "active" : ""}">${i}</button>`;
        }

        if (currentPage < maxPages - 2) {
            paginationHTML += `<span class="pagination-ellipsis pagination-feature-btn">...</span>`;
        }

        if (maxPages > 1) {
            paginationHTML += `<button data-page="${maxPages}" class="pagination-feature-btn ${currentPage === maxPages ? "active" : ""}">${maxPages}</button>`;
        }

        if (currentPage < maxPages) {
            paginationHTML += `<button data-page="${currentPage + 1}" class="pagination-feature-btn text-btn"><i class="fa-light fa-chevron-right"></i></button>`;
        }

        $pagination.html(paginationHTML);
    }

    // Load danh mục cha khi trang được tải
    getParentCategories();
    
    // Load bài viết mặc định khi trang được tải
    filterPosts();

    // Xử lý sự kiện khi chọn danh mục cha
    $('.idea-lvl1 select').on('change', function() {
        selectedParentId = $(this).val() || null;
        selectedChildId = null;
        currentPage = 1;
        
        if (selectedParentId) {
            getChildCategories(selectedParentId);
        } else {
            updateChildSelect([]);
        }
        filterPosts();
    });

    // Xử lý sự kiện khi chọn danh mục con
    $('.idea-lvl2 select').on('change', function() {
        selectedChildId = $(this).val() || null;
        currentPage = 1;
        filterPosts();
    });

    // Xử lý sự kiện phân trang
    $(document).on('click', '.pagination-feature-btn', function(e) {
        e.preventDefault();
        if ($(this).hasClass('pagination-ellipsis')) return;
        
        currentPage = $(this).data('page');
        filterPosts();
        $('html, body').animate({
            scrollTop: $('.inner-list-idea').offset().top - 100
        }, 500);
    });

    // Xử lý sự kiện tìm kiếm
    $('.searchText').on('input', function(e) {
        const keyword = $(this).val().trim();
        
        // Xóa timeout cũ nếu có
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // Nếu từ khóa rỗng, tìm kiếm ngay lập tức
        if (keyword === '') {
            searchKeyword = '';
            currentPage = 1;
            filterPosts();
            return;
        }

        // Chỉ tìm kiếm khi từ khóa có độ dài >= 2
        if (keyword.length >= 2) {
            searchTimeout = setTimeout(function() {
                searchKeyword = keyword;
                currentPage = 1;
                filterPosts();
            }, 1000); // Delay 500ms từ lần nhập cuối cùng
        }
    });
}); 