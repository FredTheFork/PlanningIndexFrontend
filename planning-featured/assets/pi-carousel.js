document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-pi-featuredapps-carousel]").forEach(container => {
        const track = container.querySelector(".pi-featuredapps-track");
        const originalCards = [...track.querySelectorAll(".pi-featuredapps-card")];
        if (originalCards.length === 0) return;

        const GAP = 20;
        let CARD_WIDTH = originalCards[0].getBoundingClientRect().width + GAP;
        const ITEM_COUNT = originalCards.length;
        const SPEED = 1;

        const fragment = document.createDocumentFragment();

        for (let i = 0; i < 2; i++) {
            originalCards.forEach(card => {
                fragment.appendChild(card.cloneNode(true));
            });
        }

        track.appendChild(fragment);

        for (let i = 0; i < 2; i++) {
            originalCards.forEach(card => {
                track.appendChild(card.cloneNode(true));
            });
        }

        let pos = ITEM_COUNT * CARD_WIDTH * 2;

        let raf = null;

        const animate = () => {
            pos += SPEED;
            track.style.transform = `translate3d(${-pos}px, 0, 0)`;

            if (pos >= (ITEM_COUNT * CARD_WIDTH * 3)) {
                pos -= (ITEM_COUNT * CARD_WIDTH * 2);
            }

            if (pos < (ITEM_COUNT * CARD_WIDTH)) {
                pos += (ITEM_COUNT * CARD_WIDTH * 2);
            }

            highlightCenterCard();
            raf = requestAnimationFrame(animate);
        };

        const highlightCenterCard = () => {
            const centerX = window.innerWidth / 2;
            let bestCard = null;
            let bestDist = Infinity;

            track.querySelectorAll(".pi-featuredapps-card").forEach(card => {
                const rect = card.getBoundingClientRect();
                const cardCenter = rect.left + rect.width / 2;
                const distance = Math.abs(cardCenter - centerX);

                card.classList.remove("pi-featuredapps-active");

                if (distance < bestDist) {
                    bestDist = distance;
                    bestCard = card;
                }
            });

            if (bestCard && bestDist < (window.innerWidth / 2)) {
                bestCard.classList.add("pi-featuredapps-active");
            }
        };

        track.style.transition = "none";
        track.style.transform = `translate3d(${-pos}px, 0, 0)`;
        raf = requestAnimationFrame(animate);
        setTimeout(highlightCenterCard, 100);

        container.addEventListener("mouseenter", () => cancelAnimationFrame(raf));
        container.addEventListener("mouseleave", () => raf = requestAnimationFrame(animate));

        let dragging = false;
        let startX = 0;
        let startPos = 0;

        const startDrag = (x) => {
            dragging = true;
            startX = x;
            startPos = pos;
            cancelAnimationFrame(raf);
            track.style.transition = "none";
        };

        const drag = (x) => {
            if (!dragging) return;
            const delta = (startX - x) * 1.2;
            pos = startPos + delta;
            track.style.transform = `translate3d(${-pos}px, 0, 0)`;
            highlightCenterCard();
        };

        const endDrag = () => {
            if (dragging) {
                dragging = false;
                raf = requestAnimationFrame(animate);
            }
        };

        container.addEventListener("mousedown", e => {
            e.preventDefault();
            startDrag(e.clientX);
        });

        container.addEventListener("touchstart", e => {
            startDrag(e.touches[0].clientX);
            e.preventDefault();
        }, { passive: false });

        window.addEventListener("mousemove", e => drag(e.clientX));
        window.addEventListener("touchmove", e => drag(e.touches[0].clientX), { passive: false });

        window.addEventListener("mouseup", endDrag);
        window.addEventListener("touchend", endDrag);

        const handleResize = () => {
            CARD_WIDTH = originalCards[0].getBoundingClientRect().width + GAP;
            const progress = (pos % (ITEM_COUNT * CARD_WIDTH)) / (ITEM_COUNT * CARD_WIDTH);
            pos = (ITEM_COUNT * CARD_WIDTH * 2) + (progress * ITEM_COUNT * CARD_WIDTH);
            track.style.transform = `translate3d(${-pos}px, 0, 0)`;
            highlightCenterCard();
        };

        window.addEventListener("resize", handleResize);
    });
});
