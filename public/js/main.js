document.addEventListener('DOMContentLoaded', () => {
    // Live loan calculator
    const loanAmount = document.querySelector('#loanAmount');
    const loanAmountDisplay = document.querySelector('#loanAmountDisplay');
    const monthlyPayment = document.querySelector('#monthlyPayment');
    const termInputs = document.querySelectorAll('input[name="loan_term"]');
    const annualRate = 4.99;

    const peso = (value) => new Intl.NumberFormat('en-PH', {
        style: 'currency', currency: 'PHP', minimumFractionDigits: 2
    }).format(value).replace('PHP', '₱');

    function calculateMonthlyPayment() {
        if (!loanAmount || !monthlyPayment) return;
        const principal = Number(loanAmount.value);
        const term = Number(document.querySelector('input[name="loan_term"]:checked')?.value || 12);
        const monthlyRate = (annualRate / 100) / 12;
        const payment = principal * (monthlyRate * Math.pow(1 + monthlyRate, term)) /
            (Math.pow(1 + monthlyRate, term) - 1);

        loanAmountDisplay.textContent = peso(principal);
        monthlyPayment.textContent = `${peso(payment)}/month`;
    }

    loanAmount?.addEventListener('input', calculateMonthlyPayment);
    termInputs.forEach(input => input.addEventListener('change', calculateMonthlyPayment));
    calculateMonthlyPayment();

    // Accessible FAQ accordion
    document.querySelectorAll('[data-faq]').forEach(button => {
        button.addEventListener('click', () => {
            const answer = document.getElementById(button.getAttribute('aria-controls'));
            const isOpen = button.getAttribute('aria-expanded') === 'true';

            document.querySelectorAll('[data-faq]').forEach(otherButton => {
                otherButton.setAttribute('aria-expanded', 'false');
                const otherAnswer = document.getElementById(otherButton.getAttribute('aria-controls'));
                otherAnswer?.classList.remove('is-open');
            });

            if (!isOpen) {
                button.setAttribute('aria-expanded', 'true');
                answer?.classList.add('is-open');
            }
        });
    });

    // Testimonial carousel
    const carousel = document.querySelector('[data-carousel]');
    if (carousel) {
        const slides = [...carousel.querySelectorAll('[data-slide]')];
        const dots = [...document.querySelectorAll('[data-carousel-dot]')];
        let current = 0;
        let timer;

        function showSlide(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => {
                const active = i === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
            });
            dots.forEach((dot, i) => {
                const active = i === current;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', String(active));
            });
        }

        function restartAutoplay() {
            clearInterval(timer);
            timer = setInterval(() => showSlide(current + 1), 6000);
        }

        carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => {
            showSlide(current - 1);
            restartAutoplay();
        });
        carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => {
            showSlide(current + 1);
            restartAutoplay();
        });
        dots.forEach((dot, i) => dot.addEventListener('click', () => {
            showSlide(i);
            restartAutoplay();
        }));

        carousel.addEventListener('mouseenter', () => clearInterval(timer));
        carousel.addEventListener('mouseleave', restartAutoplay);
        showSlide(0);
        restartAutoplay();
    }

    // Smoothly close Bootstrap mobile menu after navigation.
    document.querySelectorAll('#mainNavigation .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            const menu = document.querySelector('#mainNavigation');
            if (menu?.classList.contains('show') && window.bootstrap) {
                bootstrap.Collapse.getOrCreateInstance(menu).hide();
            }
        });
    });
});
