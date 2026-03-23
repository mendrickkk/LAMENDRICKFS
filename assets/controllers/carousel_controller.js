import { Controller } from '@hotwired/stimulus';

/*
 * Carousel Controller for Hero Section
 * Handles slide navigation, dot navigation, and arrow buttons
 */
export default class extends Controller {
    static targets = ['slide', 'caption', 'dot'];
    static values = {
        interval: { type: Number, default: 5000 },
        current: { type: Number, default: 0 }
    };

    connect() {
        this.currentIndex = this.currentValue;
        this.totalSlides = this.slideTargets.length;
        this.autoPlayInterval = null;
        this.isPaused = false;
        this.isManualControl = false;
        
        // Mark all elements as JS-controlled to disable CSS animations
        this.disableCSSAnimations();
        
        // Initialize first slide
        this.showSlide(this.currentIndex);
        
        // Start auto-play
        this.startAutoPlay();
    }

    disableCSSAnimations() {
        // Add a class or data attribute to disable CSS animations
        this.slideTargets.forEach(slide => {
            slide.style.animation = 'none';
        });
        this.captionTargets.forEach(caption => {
            caption.style.animation = 'none';
        });
        this.dotTargets.forEach(dot => {
            dot.style.animation = 'none';
        });
        this.isManualControl = true;
    }

    disconnect() {
        this.stopAutoPlay();
    }

    // Go to specific slide (used by dots)
    go(event) {
        const index = parseInt(event.currentTarget.dataset.index);
        this.showSlide(index);
        this.resetAutoPlay();
    }

    // Go to next slide (used by right arrow)
    next() {
        const nextIndex = (this.currentIndex + 1) % this.totalSlides;
        this.showSlide(nextIndex);
        this.resetAutoPlay();
    }

    // Go to previous slide (used by left arrow)
    previous() {
        const prevIndex = (this.currentIndex - 1 + this.totalSlides) % this.totalSlides;
        this.showSlide(prevIndex);
        this.resetAutoPlay();
    }

    // Show specific slide
    showSlide(index) {
        // Ensure CSS animations are disabled
        if (!this.isManualControl) {
            this.disableCSSAnimations();
        }
        
        // Remove active class from all slides, captions, and dots
        this.slideTargets.forEach(slide => {
            slide.classList.remove('active');
        });
        
        this.captionTargets.forEach(caption => {
            caption.classList.remove('active');
        });
        
        this.dotTargets.forEach(dot => {
            dot.classList.remove('active');
        });

        // Add active class to current slide, caption, and dot
        if (this.slideTargets[index]) {
            this.slideTargets[index].classList.add('active');
        }
        
        if (this.captionTargets[index]) {
            this.captionTargets[index].classList.add('active');
        }
        
        if (this.dotTargets[index]) {
            this.dotTargets[index].classList.add('active');
        }

        this.currentIndex = index;
        this.currentValue = index;
    }

    // Start auto-play
    startAutoPlay() {
        if (!this.isPaused) {
            this.autoPlayInterval = setInterval(() => {
                this.next();
            }, this.intervalValue);
        }
    }

    // Stop auto-play
    stopAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    // Reset auto-play (pause and resume after delay)
    resetAutoPlay() {
        this.stopAutoPlay();
        this.isPaused = true;
        
        // Resume auto-play after 10 seconds of no interaction
        setTimeout(() => {
            this.isPaused = false;
            this.startAutoPlay();
        }, 10000);
    }

    // Pause on hover
    pause() {
        this.stopAutoPlay();
    }

    // Resume on mouse leave (if not manually paused)
    resume() {
        if (!this.isPaused) {
            this.startAutoPlay();
        }
    }
}

