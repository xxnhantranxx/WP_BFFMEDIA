/**
 * Quản lý và khởi tạo tất cả Swiper Sliders
 */
(function() {
    'use strict';

    // Lưu trữ các instance Swiper đã khởi tạo
    const swiperInstances = {};

    // Cấu hình các slider
    const sliderConfigs = {
        HomeSlider: {
            selector: '.HomeSlider',
            options: {
                loop: true,
                slidesPerView: 1,
                grabCursor: true,
                effect: 'fade',
                autoHeight: true,
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.pagination-home-slider',
                    clickable: true,
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: '.navigation-next',
                    prevEl: '.navigation-prev',
                },
                on: {
                    autoplayTimeLeft(s, time, progress) {
                        const progressCircle = document.querySelector('.autoplay-progress svg');
                        const progressContent = document.querySelector('.autoplay-progress span');
                        if (progressCircle && progressContent) {
                            progressCircle.style.setProperty('--progress', 1 - progress);
                            progressContent.textContent = `${Math.ceil(time / 1000)}`;
                        }
                    }
                }
            }
        },
        BlogsBFF: {
            selector: '.SlideBlogsBFF',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 4,
                spaceBetween: 24,
                // slidesPerGroup: 3,
                navigation: {
                    nextEl: ".cntt-button-blogs-next",
                    prevEl: ".cntt-button-blogs-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 3,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 4,
                        spaceBetween: 24,
                    },
                },
            }
        },
        ServiceOther: {
            selector: '.SlideServiceOther',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 4,
                spaceBetween: 24,
                // slidesPerGroup: 3,
                navigation: {
                    nextEl: ".cntt-button-service-other-next",
                    prevEl: ".cntt-button-service-other-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 3,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 4,
                        spaceBetween: 24,
                    },
                },
            }
        },
        ReviewsHome: {
            selector: '.Reviews',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 3,
                spaceBetween: 20,
                navigation: {
                    nextEl: ".cntt-button-reviews-next",
                    prevEl: ".cntt-button-reviews-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 1,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 2,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 3,
                        spaceBetween: 20,
                    },
                },
            }
        },
        Tours: {
            selector: '.SlideTours',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 4,
                spaceBetween: 20,
                navigation: {
                    nextEl: ".cntt-button-tours-next",
                    prevEl: ".cntt-button-tours-prev",
                },
                grabCursor: true,
                centeredSlides: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 1.5,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 3,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                },
            }
        },
        Album:{
            selector: '.HotTrendAlbumSlider',
            options: {
                loop: true,
                slidesPerView: 1,
                spaceBetween: 20,
                grabCursor: true,
                // autoHeight: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".cntt-button-album-next",
                    prevEl: ".cntt-button-album-prev",
                },
            }
        },
        Gallery:{
            selector: '._3rac',
            options: {
                loop: true,
                slidesPerView: 1,
                spaceBetween: 20,
                grabCursor: true,
                autoplay: {
                    delay: 2000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: ".cntt-button-gallery-next",
                    prevEl: ".cntt-button-gallery-prev",
                },
            }
        },
        SlideThueTrangPhuc: {
            selector: '.SlideThueTrangPhuc ',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 3,
                spaceBetween: 20,
                navigation: {
                    nextEl: ".cntt-button-thue-next",
                    prevEl: ".cntt-button-thue-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3500,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 1,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 2,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 3,
                        spaceBetween: 20,
                    },
                },
            }
        },
        SlideGoiTour: {
            selector: '.SlideGoiTour ',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                slidesPerView: 3,
                spaceBetween: 20,
                navigation: {
                    nextEl: ".cntt-button-thue-tours-next",
                    prevEl: ".cntt-button-thue-tours-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    320: { 
                        slidesPerView: 1,
                        spaceBetween: 10,
                    },
                    768: { 
                        slidesPerView: 2,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 3,
                        spaceBetween: 20,
                    },
                },
            }
        },
        SlideMakeUp: {
            selector: '.SlideMakeUp ',
            options: {
                loop: true, // Bật chế độ vòng lặp vô hạn
                spaceBetween: 20,
                navigation: {
                    nextEl: ".cntt-button-make-up-next",
                    prevEl: ".cntt-button-make-up-prev",
                },
                grabCursor: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                breakpoints: {
                    768: { 
                        slidesPerView: 1,
                        spaceBetween: 16,
                    },
                    1024: { 
                        slidesPerView: 2,
                        spaceBetween: 20,
                    },
                },
            }
        },
        QuyTrinh: {
            selector: '.QuyTrinh',
            options: {
                loop: false, // Bật chế độ vòng lặp vô hạn
                    slidesPerView: 1,
                    // spaceBetween: 16,
                    // slidesPerGroup: 3,
                    navigation: {
                        nextEl: ".cntt-button-next",
                        prevEl: ".cntt-button-prev",
                    },
                    grabCursor: true,
                    // autoplay: {
                    //     delay: 5000,
                    //     disableOnInteraction: false,
                    // },
                    // freeMode: true,
                    // pagination: {
                    //     el: '.swiper-pagination',
                    //     clickable: true,
                    // },
                    breakpoints: {
                        320: { 
                            slidesPerView: 1.5,
                        },
                        768: { 
                            slidesPerView: 3,
                        },
                        1024: { 
                            slidesPerView: 4,
                        },
                    },
            }
        },
    };

    /**
     * Kiểm tra xem có đang trong UX Builder không
     */
    function isUXBuilder() {
        return window.self !== window.top && 
               (document.querySelector('#ux-builder') !== null || 
                window.location.href.includes('uxbuilder') ||
                document.body.classList.contains('uxbuilder'));
    }

    /**
     * Khởi tạo một slider cụ thể
     */
    function initSlider(name, config) {
        const element = document.querySelector(config.selector);
        if (!element) return;

        // Kiểm tra xem có wrapper không (cần thiết cho Swiper)
        const wrapper = element.querySelector('.swiper-wrapper');
        if (!wrapper) {
            // Nếu không có wrapper, tạo một wrapper rỗng trong UX Builder
            const inUXBuilder = isUXBuilder();
            if (inUXBuilder) {
                const tempWrapper = document.createElement('div');
                tempWrapper.className = 'swiper-wrapper';
                element.appendChild(tempWrapper);
            } else {
                return;
            }
        }

        // Trong UX Builder, cho phép khởi tạo ngay cả khi không có slides
        // Ngoài UX Builder, yêu cầu phải có slides
        const hasSlides = element.querySelector('.swiper-slide');
        const inUXBuilder = isUXBuilder();
        
        if (!inUXBuilder && !hasSlides) return;

        // Destroy instance cũ nếu tồn tại
        if (swiperInstances[name]?.destroy) {
            swiperInstances[name].destroy(true, true);
        }

        try {
            swiperInstances[name] = new Swiper(config.selector, config.options);
            console.log(`${name} đã được khởi tạo`);
        } catch (error) {
            console.error(`Lỗi khi khởi tạo ${name}:`, error);
        }
    }

    /**
     * Khởi tạo tất cả slider
     */
    function initAllSliders() {
        Object.keys(sliderConfigs).forEach(name => {
            initSlider(name, sliderConfigs[name]);
        });
    }

    /**
     * Kiểm tra xem node có chứa slider nào không
     */
    function hasSlider(node) {
        return Object.values(sliderConfigs).some(config => {
            return node.matches?.(config.selector) || node.querySelector?.(config.selector);
        });
    }

    /**
     * Khởi tạo khi DOM ready
     */
    function onReady() {
        initAllSliders();
        
        // MutationObserver để detect khi slider được thêm vào DOM
        const observer = new MutationObserver(mutations => {
            const shouldInit = mutations.some(mutation => 
                Array.from(mutation.addedNodes).some(node => 
                    node.nodeType === 1 && hasSlider(node)
                )
            );
            if (shouldInit) setTimeout(initAllSliders, 100);
        });

        observer.observe(document.body, { childList: true, subtree: true });

        // Lắng nghe sự kiện từ UX Builder
        window.addEventListener('message', event => {
            if (event.data?.source === 'uxbuilder') {
                // Retry nhiều lần với delay tăng dần để đảm bảo DOM sẵn sàng
                setTimeout(initAllSliders, 100);
                setTimeout(initAllSliders, 300);
                setTimeout(initAllSliders, 500);
            }
        });

        // Thêm observer đặc biệt cho UX Builder - retry khi có thay đổi
        if (isUXBuilder()) {
            const uxObserver = new MutationObserver(() => {
                setTimeout(initAllSliders, 200);
            });
            uxObserver.observe(document.body, { 
                childList: true, 
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            });
        }
    }

    // Khởi động
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

})();
