// Dark mode toggle
function toggleDarkMode() {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
    }

    // Check for saved dark mode preference
    if (localStorage.getItem('darkMode') === 'true' || 
        (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }

    // Initialize Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('layout', () => ({
            sidebarCollapsed: false,
            userMenuOpen: false,
            isMobile: window.innerWidth < 768,

            init() {
                this.handleResize();
                window.addEventListener('resize', () => this.handleResize());
            },

            handleResize() {
                this.isMobile = window.innerWidth < 768;
                if (this.isMobile) {
                    this.sidebarCollapsed = true;
                }
            }
        }))
    })



    function quizLoader() {
        return {
            quizLoading: true,
            quizError: false,
            quizData: null,
            
            init() {
                this.loadQuiz();
            },

            async loadQuiz() {
                this.quizLoading = true;
                this.quizError = false;
                try {
                    const sectionId = Alpine.store('content').activeContent;
                    console.log('Loading quiz for section ID:', sectionId);
                    const response = await fetch(`api/get_quiz.php?section_id=${sectionId}`);
                    const data = await response.json();
                    console.log('Quiz API response:', data);
                    
                    if (data.error) {
                        this.quizError = true;
                        console.error('Quiz error:', data.error);
                    } else {
                        this.quizData = data;
                        // Store quiz data in Alpine store so quiz component can access it
                        Alpine.store('content').quizData = data;
                        console.log('Quiz data stored in Alpine store:', data);
                        this.quizError = false;
                    }
                } catch (error) {
                    console.error('Error loading quiz:', error);
                    this.quizError = true;
                } finally {
                    this.quizLoading = false;
                }
            },
            
            initQuizData() {
                console.log('initQuizData called - reloading quiz data');
                this.quizData = null;
                this.quizError = false;
                this.quizLoading = false;
                this.loadQuiz();
            }
        }
}