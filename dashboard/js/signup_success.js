// Success Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    let countdown = 3;
    const countdownElement = document.getElementById('countdown');
    const progressBar = document.querySelector('.progress-bar');
    const successCard = document.querySelector('.success-card');
    
    // Update countdown and progress bar
    function updateCountdown() {
        countdownElement.textContent = countdown;
        
        // Update progress bar (100% / 3 seconds = 33.33% per second)
        const progress = ((3 - countdown) / 3) * 100;
        progressBar.style.width = progress + '%';
        
        if (countdown <= 0) {
            // Add fade out animation before redirect
            successCard.classList.add('fade-out');
            
            // Redirect after fade out animation
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 500);
        } else {
            countdown--;
            setTimeout(updateCountdown, 1000);
        }
    }
    
    // Start countdown after a brief delay to let animations play
    setTimeout(updateCountdown, 1000);
    
    // Add click handler to stop countdown if user clicks login manually
    const loginButton = document.querySelector('a[href="login.php"]');
    loginButton.addEventListener('click', function() {
        countdown = -1; // Stop the countdown
        progressBar.style.width = '100%';
    });
    
    // Add keyboard accessibility
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            loginButton.click();
        }
        if (e.key === 'Escape') {
            // Allow user to cancel redirect with Escape key
            countdown = -1;
            countdownElement.textContent = 'Cancelled';
            progressBar.style.width = '0%';
            document.querySelector('.text-gray-400').textContent = 'Auto-redirect cancelled. Click "Login Now" to continue.';
        }
    });
    
    // Add smooth scroll to top on load
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Preload login page for faster transition
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = 'login.php';
    document.head.appendChild(link);
    
    // Add subtle particle effect (optional enhancement)
    createParticles();
});

// Optional: Create subtle floating particles
function createParticles() {
    const particleCount = 20;
    const body = document.body;
    
    for (let i = 0; i < particleCount; i++) {
        setTimeout(() => {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                pointer-events: none;
                z-index: 0;
                left: ${Math.random() * 100}vw;
                top: ${Math.random() * 100}vh;
                animation: float ${5 + Math.random() * 10}s infinite linear;
            `;
            
            body.appendChild(particle);
            
            // Remove particle after animation
            setTimeout(() => {
                if (particle.parentNode) {
                    particle.parentNode.removeChild(particle);
                }
            }, 15000);
        }, i * 200);
    }
}

// Add CSS for particle animation
const style = document.createElement('style');
style.textContent = `
    @keyframes float {
        0% {
            transform: translateY(100vh) translateX(0px) rotate(0deg);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(-100vh) translateX(${Math.random() * 200 - 100}px) rotate(360deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
