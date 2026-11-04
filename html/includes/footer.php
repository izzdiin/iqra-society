<!-- FOOTER -->
<footer id="kontak" class="bg-[#053c2e] text-white pt-12 pb-6 border-t border-emerald-900/50 relative overflow-hidden">
    <div class="absolute right-0 bottom-0 opacity-10 pointer-events-none translate-x-10 translate-y-10">
        <i class="fa-solid fa-mosque text-[280px]"></i>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 pb-10 border-b border-emerald-800/60">
            
            <div class="lg:col-span-4 pr-0 lg:pr-4">
                <a href="#" class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-full bg-white border border-white/20 flex items-center justify-center text-white font-bold text-lg">
                        <img src="/asset/img/logo_kitab.png">
                    </div>
                    <div class="flex flex-col font-['Zilla_Slab'] leading-tight">
                        <span class="text-xl font-bold text-white tracking-wide uppercase leading-none">IQRA'</span>
                        <span class="text-base font-semibold text-lime-500 tracking-normal leading-none mt-0.5">Society</span>
                    </div>
                </a>
                <p class="text-xs text-emerald-100/80 leading-relaxed max-w-sm mb-6">
                    Komunitas pendidikan Islam yang berfokus pada pembelajaran, diskusi, literasi, dan pengembangan masyarakat melalui ilmu yang bermanfaat.
                </p>
                <div class="flex items-center gap-2.5">
                    <a href="https://youtube.com/@iqrasociety-r5l" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-youtube"></i>
                    </a>
                    <a href="https://instagram.com/iqrasociety.official/" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 border border-white/15 flex items-center justify-center text-white text-xs transition">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                </div>
            </div>

            <div class="lg:col-span-3">
                <h4 class="text-sm font-bold text-white mb-4">Navigasi</h4>
                <div class="grid grid-cols-2 gap-x-2 gap-y-2.5 text-xs text-emerald-100/80 font-medium">
                    <a href="/" class="hover:text-emerald-300 transition">Beranda</a>
                    <a href="/kelas" class="hover:text-emerald-300 transition">Kelas Online</a>
                    <a href="/tentang-kami" class="hover:text-emerald-300 transition">Tentang</a>
                    <a href="/#donasi" class="hover:text-emerald-300 transition">Donasi</a>
                    <a href="/#program" class="hover:text-emerald-300 transition">Program</a>
                    <a href="/#pengurus" class="hover:text-emerald-300 transition">Pengurus</a>
                    <a href="/kegiatan" class="hover:text-emerald-300 transition">Kegiatan</a>
                    <a href="/buku" class="hover:text-emerald-300 transition">Buku</a>
                </div>
            </div>

            <div class="lg:col-span-2">
                <h4 class="text-sm font-bold text-white mb-4">Kontak Kami</h4>
                <ul class="space-y-3 text-xs text-emerald-100/80">
                    <li class="flex items-start gap-2.5">
                        <i class="fa-solid fa-location-dot text-emerald-300 mt-0.5 text-xs shrink-0"></i>
                        <span>Jakarta, Indonesia</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-envelope text-emerald-300 text-xs shrink-0"></i>
                        <a href="mailto:support@iqrasociety.id" class="hover:text-white transition">support@iqrasociety.id</a>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-solid fa-phone text-emerald-300 text-xs shrink-0"></i>
                        <a href="tel:+6289646961458" class="hover:text-white transition">+62 896-4696-1458</a>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <i class="fa-brands fa-whatsapp text-emerald-300 text-sm shrink-0"></i>
                        <a href="https://wa.me/6289646961458" class="hover:text-white transition">+62 896-4696-1458</a>
                    </li>
                </ul>
            </div>

            <div class="lg:col-span-3">
                <h4 class="text-sm font-bold text-white mb-4">Newsletter</h4>
                <p class="text-xs text-emerald-100/80 mb-4 leading-relaxed">
                    Dapatkan update kegiatan dan konten terbaru dari kami.
                </p>
                <form onsubmit="event.preventDefault();" class="relative">
                    <input type="email" placeholder="Masukkan email Anda" class="w-full bg-white text-gray-900 text-xs placeholder-gray-400 pl-4 pr-11 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-400 font-medium shadow-sm">
                    <button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 bg-transparent hover:bg-emerald-50 rounded-lg text-emerald-800 flex items-center justify-center transition">
                        <i class="fa-solid fa-paper-plane text-emerald-700 text-xs"></i>
                    </button>
                </form>
            </div>

        </div>

        <div class="pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-emerald-200/60 gap-3 text-center sm:text-left">
            <p>&copy; <?php echo date('Y'); ?> IQRA' Society. All Rights Reserved.</p>
            <div class="flex items-center gap-4">
                <a href="/kebijakan-privasi" class="hover:text-white transition">Kebijakan Privasi</a>
                <span>|</span>
                <a href="/syarat-ketentuan" class="hover:text-white transition">Syarat & Ketentuan</a>
            </div>
        </div>
    </div>
</footer>

<!-- FLOATING BACK TO TOP BUTTON -->
<button id="backToTopBtn" aria-label="Kembali ke atas" class="fixed bottom-6 right-6 z-50 w-11 h-11 rounded-full bg-emerald-800 hover:bg-emerald-900 text-white shadow-lg border border-emerald-700/30 flex items-center justify-center transition-all duration-300 opacity-0 pointer-events-none translate-y-4 hover:scale-110 active:scale-95">
    <i class="fa-solid fa-arrow-up text-sm"></i>
</button>

<!-- JS LIBRARIES -->
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuIcon = document.getElementById('menu-icon');
    const mobileLinks = document.querySelectorAll('.mobile-link');
    const backToTopBtn = document.getElementById('backToTopBtn');
    const navLinks = document.querySelectorAll('.nav-link');

    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            easing: 'ease-in-out',
            offset: 80,
            disable: false
        });
    }


    if (menuBtn && mobileMenu && menuIcon) {

        function setMobileMenu(open) {
            mobileMenu.classList.toggle('hidden', !open);

            menuIcon.classList.toggle('fa-bars', !open);
            menuIcon.classList.toggle('fa-xmark', open);

            menuBtn.setAttribute('aria-expanded', String(open));
            menuBtn.setAttribute(
                'aria-label',
                open ? 'Tutup Menu Navigasi' : 'Buka Menu Navigasi'
            );
        }

        setMobileMenu(false);

        menuBtn.addEventListener('click', function () {
            const isOpen = !mobileMenu.classList.contains('hidden');
            setMobileMenu(!isOpen);
        });

        mobileLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                setMobileMenu(false);
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                setMobileMenu(false);
            }
        });
    }


    if (backToTopBtn) {

        let backToTopTicking = false;

        function updateBackToTop() {
            const visible = window.scrollY > 300;

            backToTopBtn.classList.toggle('opacity-100', visible);
            backToTopBtn.classList.toggle('pointer-events-auto', visible);
            backToTopBtn.classList.toggle('translate-y-0', visible);

            backToTopBtn.classList.toggle('opacity-0', !visible);
            backToTopBtn.classList.toggle('pointer-events-none', !visible);
            backToTopBtn.classList.toggle('translate-y-4', !visible);

            backToTopTicking = false;
        }

        function requestBackToTopUpdate() {
            if (!backToTopTicking) {
                backToTopTicking = true;
                window.requestAnimationFrame(updateBackToTop);
            }
        }

        window.addEventListener(
            'scroll',
            requestBackToTopUpdate,
            { passive: true }
        );

        updateBackToTop();

        backToTopBtn.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }


    if (
        navLinks.length > 0 &&
        'IntersectionObserver' in window
    ) {
        const sections = document.querySelectorAll(
            'section[id], footer[id]'
        );

        const sectionMap = new Map();

        navLinks.forEach(function (link) {
            const href = link.getAttribute('href');

            if (href && href.charAt(0) === '#') {
                sectionMap.set(href.substring(1), link);
            }
        });

        const navObserver = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    const id = entry.target.getAttribute('id');

                    navLinks.forEach(function (link) {
                        link.classList.remove('active');
                    });

                    const activeLink = sectionMap.get(id);

                    if (activeLink) {
                        activeLink.classList.add('active');
                    }
                });
            },
            {
                root: null,
                rootMargin: '-96px 0px -55% 0px',
                threshold: 0
            }
        );

        sections.forEach(function (section) {
            navObserver.observe(section);
        });
    }


    if (typeof Swiper !== 'undefined') {

        const mobileSwiperConfigs = [
            {
                selector: '.pengurusSwiper',
                instance: null,
                options: {
                    slidesPerView: 2.2,
                    spaceBetween: 16,
                    centeredSlides: false,
                    watchOverflow: true,
                    pagination: {
                        el: '.pengurus-pagination',
                        clickable: true
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 2.8,
                            spaceBetween: 16
                        },
                        640: {
                            slidesPerView: 3.5,
                            spaceBetween: 20
                        },
                        768: {
                            slidesPerView: 4.5,
                            spaceBetween: 20
                        }
                    }
                }
            },
            {
                selector: '.programSwiper',
                instance: null,
                options: {
                    slidesPerView: 1,
                    spaceBetween: 16,
                    watchOverflow: true,
                    pagination: {
                        el: '.program-pagination',
                        clickable: true
                    },
                    breakpoints: {
                        640: {
                            slidesPerView: 2,
                            spaceBetween: 20
                        },
                        768: {
                            slidesPerView: 3,
                            spaceBetween: 20
                        }
                    }
                }
            },
            {
                selector: '.kegiatanSwiper',
                instance: null,
                options: {
                    slidesPerView: 1.2,
                    spaceBetween: 16,
                    watchOverflow: true,
                    pagination: {
                        el: '.kegiatan-pagination',
                        clickable: true
                    },
                    breakpoints: {
                        480: {
                            slidesPerView: 1.8,
                            spaceBetween: 16
                        },
                        640: {
                            slidesPerView: 2.2,
                            spaceBetween: 20
                        },
                        768: {
                            slidesPerView: 3.2,
                            spaceBetween: 20
                        }
                    }
                }
            }
        ];


        function updateMobileSwipers() {
            const isMobile = window.innerWidth < 1024;

            mobileSwiperConfigs.forEach(function (item) {
                const element = document.querySelector(item.selector);

                if (!element) {
                    return;
                }

                if (isMobile) {
                    if (!item.instance) {
                        item.instance = new Swiper(
                            element,
                            item.options
                        );
                    }
                } else {
                    if (item.instance) {
                        item.instance.destroy(true, true);
                        item.instance = null;
                    }
                }
            });
        }


        const bukuElement =
            document.querySelector('.bukuSwiper');

        let bukuSwiper = null;

        if (bukuElement) {
            bukuSwiper = new Swiper(bukuElement, {
                slidesPerView: 2,
                spaceBetween: 12,
                watchOverflow: true,

                navigation: {
                    nextEl: '.buku-next-btn',
                    prevEl: '.buku-prev-btn'
                },

                breakpoints: {
                    640: {
                        slidesPerView: 3,
                        spaceBetween: 12
                    },
                    1024: {
                        slidesPerView: 4,
                        spaceBetween: 12
                    }
                }
            });
        }


        const kelasElement =
            document.querySelector('.kelasSwiper');

        let kelasSwiper = null;

        if (kelasElement) {
            kelasSwiper = new Swiper(kelasElement, {
                slidesPerView: 2,
                spaceBetween: 12,
                watchOverflow: true,

                navigation: {
                    nextEl: '.kelas-next-btn',
                    prevEl: '.kelas-prev-btn'
                },
                
                breakpoints: {
                    640: {
                        slidesPerView: 3,
                        spaceBetween: 12
                    },
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 12
                    },
                    1280: {
                        slidesPerView: 4,
                        spaceBetween: 12
                    }
                }
            });
        }


        updateMobileSwipers();


        let resizeTimer = null;

        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);

            resizeTimer = setTimeout(function () {
                updateMobileSwipers();

                if (bukuSwiper) {
                    bukuSwiper.update();
                }

                if (kelasSwiper) {
                    kelasSwiper.update();
                }

                if (typeof AOS !== 'undefined') {
                    AOS.refreshHard();
                }
            }, 150);
        });
    }
});
</script>
</body>
</html>