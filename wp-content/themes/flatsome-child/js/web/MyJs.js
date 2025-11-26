jQuery(document).ready(function ($) {
    // Tự động thiết lập delay tăng dần cho các card-overview
    // Item đầu tiên: 0s, item thứ 2: 0.2s, item thứ 3: 0.4s, ...
    $('.card-overview').each(function(index) {
        var delay = (index * 0.2).toFixed(1) + 's';
        $(this).attr('data-wow-delay', delay);
    });
    
    // Khởi tạo WowJs
    wow = new WOW(
        {
            boxClass: 'wow',      // default
            animateClass: 'animated', // default
            offset: 0,          // default
            mobile: true,       // default
            live: true        // default
        }
    )
    wow.init();

    // Hàm khởi tạo select2
    function initSelect2() {
        $('.wpc-filters-widget-select').each(function() {
            // Kiểm tra xem đã được khởi tạo select2 chưa
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    minimumResultsForSearch: -1,
                    theme: "custom",
                    containerCssClass: ':all:',
                });
            }
        });
    }

    // Khởi tạo select2 lần đầu
    initSelect2();

    // Lắng nghe sự kiện thay đổi DOM để rebuild select2
    var observer = new MutationObserver(function(mutations) {
        var shouldRebuild = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                // Kiểm tra xem có element mới chứa class wpc-filters-widget-select không
                $(mutation.addedNodes).find('.wpc-filters-widget-select').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        shouldRebuild = true;
                    }
                });
                // Kiểm tra chính node được thêm vào
                $(mutation.addedNodes).filter('.wpc-filters-widget-select').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        shouldRebuild = true;
                    }
                });
            }
        });
        if (shouldRebuild) {
            initSelect2();
        }
    });

    // Bắt đầu quan sát thay đổi trong body
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    $('.wpcf7-select').select2({
        minimumResultsForSearch: -1,
        theme: "custom",
        containerCssClass: ':all:',
    });

    // Hàm khởi tạo Masonry sau khi tất cả hình ảnh đã load xong
    function initMasonry($grid) {
        // Kiểm tra xem grid đã có Masonry instance chưa
        if ($grid.data('masonry')) {
            // Nếu đã có, destroy instance cũ trước khi tạo mới
            $grid.masonry('destroy');
        }
        
        // Lấy tất cả hình ảnh trong grid
        var $images = $grid.find('img');
        var imagesCount = $images.length;
        
        // Hàm khởi tạo Masonry
        function setupMasonry() {
            $grid.masonry({
                itemSelector: '.grid-item',
                columnWidth: '.grid-item',
                percentPosition: true,
                gutter: 15
            });
        }
        
        // Nếu không có hình ảnh nào, khởi tạo Masonry ngay
        if (imagesCount === 0) {
            setupMasonry();
            return;
        }
        
        // Đếm số hình ảnh đã load xong
        var loadedCount = 0;
        
        // Hàm kiểm tra xem tất cả hình ảnh đã load chưa
        function checkImagesLoaded() {
            loadedCount++;
            if (loadedCount === imagesCount) {
                // Tất cả hình ảnh đã load xong, khởi tạo Masonry
                setupMasonry();
            }
        }
        
        // Lắng nghe sự kiện load của từng hình ảnh
        $images.each(function() {
            var $img = $(this);
            
            // Nếu hình ảnh đã load xong (cached), tăng counter ngay
            if ($img[0].complete && $img[0].naturalHeight !== 0) {
                checkImagesLoaded();
            } else {
                // Lắng nghe sự kiện load
                $img.one('load', checkImagesLoaded);
                
                // Xử lý trường hợp lỗi load hình ảnh
                $img.one('error', checkImagesLoaded);
            }
        });
    }
    
    // Khởi tạo Masonry cho tất cả grid hiện có
    $('.grid').each(function() {
        var $grid = $(this);
        // Đánh dấu grid đã được khởi tạo
        if (!$grid.hasClass('masonry-initialized')) {
            $grid.addClass('masonry-initialized');
            initMasonry($grid);
        }
    });
    
    // Khởi tạo lại Masonry khi có grid mới được thêm vào
    var masonryObserver = new MutationObserver(function(mutations) {
        var hasNewGrid = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                $(mutation.addedNodes).find('.grid').each(function() {
                    var $grid = $(this);
                    if (!$grid.hasClass('masonry-initialized')) {
                        hasNewGrid = true;
                        $grid.addClass('masonry-initialized');
                        initMasonry($grid);
                    }
                });
                // Kiểm tra chính node được thêm vào
                $(mutation.addedNodes).filter('.grid').each(function() {
                    var $grid = $(this);
                    if (!$grid.hasClass('masonry-initialized')) {
                        hasNewGrid = true;
                        $grid.addClass('masonry-initialized');
                        initMasonry($grid);
                    }
                });
            }
        });
    });
    
    // Quan sát thay đổi trong body để khởi tạo lại Masonry
    masonryObserver.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Toggle FAQ items
    function initFaqsToggle() {
        $('.FaqsItem .heading-faq').off('click').on('click', function() {
            var $faqItem = $(this).closest('.FaqsItem');
            var $content = $faqItem.find('.content-faq');
            var $otherItems = $('.FaqsItem').not($faqItem);
            
            // Toggle class active cho item hiện tại
            $faqItem.toggleClass('active');
            
            // Slide toggle content
            $content.slideToggle(300);
            
            // Đóng các item khác nếu muốn accordion style (mở 1 đóng các cái khác)
            // Nếu muốn mở nhiều item cùng lúc thì comment phần này
            if ($faqItem.hasClass('active')) {
                $otherItems.removeClass('active');
                $otherItems.find('.content-faq').slideUp(300);
            }
        });
        
        // Mở item đầu tiên mặc định
        var $firstItem = $('.FaqsItem').first();
        if ($firstItem.length && !$firstItem.hasClass('active')) {
            $firstItem.addClass('active');
            $firstItem.find('.content-faq').show();
        }
    }
    
    // Khởi tạo FAQ toggle lần đầu
    initFaqsToggle();
    
    // Khởi tạo lại FAQ toggle khi có DOM mới được thêm vào
    var faqObserver = new MutationObserver(function(mutations) {
        var hasNewFaqs = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                $(mutation.addedNodes).find('.FaqsItem').each(function() {
                    if (!$(this).hasClass('faq-initialized')) {
                        hasNewFaqs = true;
                        $(this).addClass('faq-initialized');
                    }
                });
                $(mutation.addedNodes).filter('.FaqsItem').each(function() {
                    if (!$(this).hasClass('faq-initialized')) {
                        hasNewFaqs = true;
                        $(this).addClass('faq-initialized');
                    }
                });
            }
        });
        if (hasNewFaqs) {
            initFaqsToggle();
        }
    });
    
    // Quan sát thay đổi trong body để khởi tạo lại FAQ toggle
    faqObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Di chuyển #price-tour vào trong ._4iby (form CF7)
    function movePriceTourTo4iby() {
        var $priceTour = $('#price-tour');
        var $target4iby = $('._4iby');
        
        // Kiểm tra nếu cả hai element đều tồn tại và price-tour chưa được di chuyển
        if ($priceTour.length && $target4iby.length && $priceTour.parent().hasClass('_4iby') === false) {
            // Di chuyển #price-tour vào trong ._4iby
            $priceTour.appendTo($target4iby);
        }
    }
    
    // Thực hiện di chuyển ngay khi DOM ready
    movePriceTourTo4iby();
    
    // Quan sát thay đổi DOM để di chuyển khi form CF7 được render
    var priceTourObserver = new MutationObserver(function(mutations) {
        var shouldMove = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                // Kiểm tra xem có element ._4iby mới được thêm vào không
                $(mutation.addedNodes).find('._4iby').each(function() {
                    if ($('#price-tour').length && $(this).find('#price-tour').length === 0) {
                        shouldMove = true;
                    }
                });
                // Kiểm tra chính node được thêm vào
                $(mutation.addedNodes).filter('._4iby').each(function() {
                    if ($('#price-tour').length && $(this).find('#price-tour').length === 0) {
                        shouldMove = true;
                    }
                });
            }
        });
        if (shouldMove) {
            movePriceTourTo4iby();
        }
    });
    
    // Quan sát thay đổi trong body để di chuyển price-tour
    priceTourObserver.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Xử lý tab switching cho FaqsPanel
    function initFaqsPanelTabs() {
        $('.FaqsPanel .tab-head-item').off('click.tabSwitch').on('click.tabSwitch', function() {
            var $clickedTab = $(this);
            var $faqsPanel = $clickedTab.closest('.FaqsPanel');
            var tabIndex = $clickedTab.index();
            
            // Xóa class active từ tất cả tab-head-item
            $faqsPanel.find('.tab-head-item').removeClass('active');
            
            // Thêm class active cho tab được click
            $clickedTab.addClass('active');
            
            // Xóa class active từ tất cả tab-content
            $faqsPanel.find('.tab-content').removeClass('active');
            
            // Thêm class active cho tab-content tương ứng
            $faqsPanel.find('.tab-content').eq(tabIndex).addClass('active');
        });
    }
    
    // Khởi tạo tab switching lần đầu
    initFaqsPanelTabs();
    
    // Khởi tạo lại tab switching khi có DOM mới được thêm vào
    var tabObserver = new MutationObserver(function(mutations) {
        var hasNewTabs = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                $(mutation.addedNodes).find('.FaqsPanel').each(function() {
                    if (!$(this).hasClass('tab-initialized')) {
                        hasNewTabs = true;
                        $(this).addClass('tab-initialized');
                    }
                });
                $(mutation.addedNodes).filter('.FaqsPanel').each(function() {
                    if (!$(this).hasClass('tab-initialized')) {
                        hasNewTabs = true;
                        $(this).addClass('tab-initialized');
                    }
                });
            }
        });
        if (hasNewTabs) {
            initFaqsPanelTabs();
        }
    });
    
    // Quan sát thay đổi trong body để khởi tạo lại tab switching
    tabObserver.observe(document.body, {
        childList: true,
        subtree: true
    });

    $('.SharePost ._2lfc').off('click').on('click', function(e) {
        e.stopPropagation(); // Ngăn event bubble lên document
        $(this).closest('.SharePost').find('._2dzo').show();
    });
    
    // Ẩn ._2dzo khi click ra ngoài
    $(document).off('click.sharePost').on('click.sharePost', function(e) {
        // Kiểm tra xem click có nằm trong .SharePost hay không
        if (!$(e.target).closest('.SharePost').length) {
            $('.SharePost ._2dzo').hide();
        }
    });
    
    // Ngăn ẩn khi click vào bên trong ._2dzo
    $('.SharePost ._2dzo').off('click').on('click', function(e) {
        e.stopPropagation(); // Ngăn event bubble lên document
    });

    $('.icon-search-customize').off('click').on('click', function(e) {
        e.stopPropagation(); // Ngăn event bubble lên document
        $('.content-search').show();
        $('.overlay-footer').show();
    });
    
    // Ẩn .content-search khi click ra ngoài
    $(document).off('click.contentSearch').on('click.contentSearch', function(e) {
        // Kiểm tra xem click có nằm trong .content-search hoặc .icon-search-customize hay không
        if (!$(e.target).closest('.content-search').length && !$(e.target).closest('.icon-search-customize').length) {
            $('.content-search').hide();
            $('.overlay-footer').hide();
        }
    });
    
    // Ngăn ẩn khi click vào bên trong .content-search
    $('.content-search').off('click').on('click', function(e) {
        e.stopPropagation(); // Ngăn event bubble lên document
    });
    $('.overlay-footer').off('click').on('click', function(e) {
        e.stopPropagation(); // Ngăn event bubble lên document
        $('.content-search').hide();
        $('.overlay-footer').hide();
    });
});
