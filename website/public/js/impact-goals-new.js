document.addEventListener("DOMContentLoaded", () => {

    /* ==========================
       SCROLL REVEAL (Hero, Summary, CTA)
    ========================== */
    const revealElements = document.querySelectorAll(".impact-goals-wrapper .hero, .impact-goals-wrapper .summary-card, .impact-goals-wrapper .cta");
    
    if (revealElements.length > 0) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("show");
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        revealElements.forEach(el => revealObserver.observe(el));
    }

    /* ==========================
       COUNTER ANIMATION
    ========================== */
    function animateCounters() {
        const counters = document.querySelectorAll(".impact-goals-wrapper .counter");
        counters.forEach(counter => {
            const target = +counter.getAttribute("data-target");
            const duration = 2000;
            const startTime = performance.now();
            
            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Ease out cubic
                const easeOut = 1 - Math.pow(1 - progress, 3);
                
                counter.textContent = Math.floor(target * easeOut).toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            }
            requestAnimationFrame(updateCounter);
        });
    }

    // Trigger counters when SVG container is in view
    const svgContainer = document.querySelector(".new-svg-chart-wrapper");
    if (svgContainer) {
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    counterObserver.unobserve(svgContainer);
                }
            });
        }, { threshold: 0.3 });
        counterObserver.observe(svgContainer);
    }

    // Below ~600px the SVG above is display:none (see impact-goals.php /
    // impact-goals-new.css) and its own observer above never fires - a
    // hidden element has no box to intersect. .chart-mobile-list is what
    // actually shows the counters at that width, so it needs the same
    // trigger; without this its counters sat at "0" until the visitor
    // scrolled all the way down to the summary cards.
    const mobileChartList = document.querySelector(".chart-mobile-list");
    if (mobileChartList) {
        const mobileListObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    mobileListObserver.unobserve(mobileChartList);
                }
            });
        }, { threshold: 0.3 });
        mobileListObserver.observe(mobileChartList);
    }

    // Also trigger counters when Summary section is in view
    const summarySection = document.querySelector(".summary");
    if (summarySection) {
        const summaryObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    summaryObserver.unobserve(summarySection);
                }
            });
        }, { threshold: 0.3 });
        summaryObserver.observe(summarySection);
    }

    /* ==========================
       CTA "Explore Our Journey" -> chart (was an inline onclick)
    ========================== */
    document.querySelectorAll("[data-scroll-to]").forEach(btn => {
        btn.addEventListener("click", () => {
            const target = document.querySelector(btn.getAttribute("data-scroll-to"));
            if (target) target.scrollIntoView({ behavior: "smooth" });
        });
    });

});
