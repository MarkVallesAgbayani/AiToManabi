<?php
// This component expects $quiz to be set with the quiz data
if (!isset($quiz)) {
    return;
}
?>
<div x-data="quizComponent(<?php echo $quiz['id']; ?>)" 
     class="quiz-container bg-white dark:bg-dark-surface rounded-lg shadow p-6 border border-gray-200 dark:border-dark-border">
    
    <!-- Quiz Header -->
    <div class="mb-8">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
            <?php echo htmlspecialchars($quiz['title'] ?? 'Section Quiz'); ?>
        </h3>
        <?php if (isset($quiz['description'])): ?>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                <?php echo htmlspecialchars($quiz['description']); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Error State -->
    <div x-show="error" class="text-center py-8">
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
            <p class="text-red-600 dark:text-red-400" x-text="error"></p>
            <button @click="init()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Try Again
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading && !error" class="text-center py-8">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-red mx-auto"></div>
        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading quiz...</p>
    </div>

    <!-- Quiz Questions -->
    <div x-show="!loading && !error && !submitted" class="space-y-8">
        <template x-for="(question, index) in (questions || [])" :key="question.id">
            <div class="question-container p-6 bg-gray-50 dark:bg-dark-border rounded-lg">
                <div class="flex items-start">
                    <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-brand-red text-white font-medium" x-text="index + 1"></span>
                    <div class="ml-4 flex-grow">
                        <p class="text-lg font-medium text-gray-900 dark:text-white mb-4" x-text="question.text"></p>
                        
                        <!-- Multiple Choice -->
                        <template x-if="question.type === 'multiple_choice'">
                            <div class="space-y-3">
                                <template x-for="choice in question.choices" :key="choice.id">
                                    <label class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors">
                                        <input type="radio" 
                                               :name="'question_' + question.id"
                                               :value="choice.id"
                                               x-model="answers[question.id]"
                                               @change="onAnswerChange()"
                                               class="h-4 w-4 text-brand-red focus:ring-brand-red">
                                        <span class="ml-3 text-gray-700 dark:text-gray-300" x-text="choice.text"></span>
                                    </label>
                                </template>
                            </div>
                        </template>

                        <!-- True/False -->
                        <template x-if="question.type === 'true_false'">
                            <div class="space-y-3">
                                <label class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors">
                                    <input type="radio" 
                                           :name="'question_' + question.id"
                                           value="true"
                                           x-model="answers[question.id]"
                                           @change="onAnswerChange()"
                                           class="h-4 w-4 text-brand-red focus:ring-brand-red">
                                    <span class="ml-3 text-gray-700 dark:text-gray-300">True</span>
                                </label>
                                <label class="flex items-center p-4 rounded-lg border border-gray-200 dark:border-dark-border cursor-pointer hover:bg-gray-100 dark:hover:bg-dark-surface transition-colors">
                                    <input type="radio" 
                                           :name="'question_' + question.id"
                                           value="false"
                                           x-model="answers[question.id]"
                                           @change="onAnswerChange()"
                                           class="h-4 w-4 text-brand-red focus:ring-brand-red">
                                    <span class="ml-3 text-gray-700 dark:text-gray-300">False</span>
                                </label>
                            </div>
                        </template>

                        <!-- Fill in the Blank -->
                        <template x-if="question.type === 'fill_blank'">
                            <div class="mt-2">
                                <input type="text" 
                                       x-model="answers[question.id]"
                                       @input="onAnswerChange()"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-brand-red focus:border-brand-red"
                                       placeholder="Enter your answer">
                            </div>
                        </template>

                        <!-- Word Definition -->
                        <template x-if="question.type === 'word_definition'">
                            <div class="space-y-4">
                                <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-700">
                                    <!-- Display question text if available -->
                                    <div x-show="question.text" class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-amber-200 dark:border-amber-700">
                                        <p class="text-sm text-amber-700 dark:text-amber-300 mb-2">Question:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-white" x-text="question.text"></p>
                                    </div>
                                    
                                    <p class="text-sm text-amber-700 dark:text-amber-300 mb-3">Match each Japanese word with its correct definition:</p>
                                    
                                    <template x-for="(pair, index) in question.word_definition_pairs" :key="index">
                                        <div class="mb-4 p-3 bg-white dark:bg-dark-surface rounded border border-gray-200 dark:border-dark-border">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-gray-800 dark:text-gray-200" x-text="pair.word"></span>
                                                <span class="text-gray-400">→</span>
                                            </div>
                                            <input type="text" 
                                                   :name="'question_' + question.id + '_' + index"
                                                   x-model="answers[question.id + '_' + index]"
                                                   @input="onAnswerChange()"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-brand-red focus:border-brand-red"
                                                   :placeholder="'Enter the definition for ' + pair.word">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>


                        <!-- Pronunciation Check -->
                        <template x-if="question.type === 'pronunciation'">
                            <div class="mt-2">
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                    <!-- Display question text if available -->
                                    <div x-show="question.text" class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-blue-200 dark:border-blue-700">
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Question:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-white" x-text="question.text"></p>
                                    </div>
                                    
                                    <!-- Display Japanese word and details if available -->
                                    <div x-show="question.word || question.romaji || question.meaning" class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-blue-200 dark:border-blue-700">
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">Please pronounce the following:</p>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                                            <div x-show="question.word">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Japanese</p>
                                                <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="question.word"></p>
                                            </div>
                                            <div x-show="question.romaji">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Romaji</p>
                                                <p class="text-lg text-gray-700 dark:text-gray-300 font-medium" x-text="question.romaji"></p>
                                            </div>
                                            <div x-show="question.meaning">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Meaning</p>
                                                <p class="text-lg text-gray-700 dark:text-gray-300 font-medium" x-text="question.meaning"></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Pronunciation hints -->
                                        <div class="mt-4 p-3 bg-blue-100 dark:bg-blue-800/30 rounded-lg">
                                            <p class="text-sm text-blue-800 dark:text-blue-200 font-medium mb-2">💡 Pronunciation Tips:</p>
                                            <ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
                                                <li>• Speak clearly and at a normal pace</li>
                                                <li>• Focus on the correct vowel sounds</li>
                                                <li>• Pay attention to pitch accent patterns</li>
                                                <li>• Record for the full 5 seconds</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Recording interface -->
                                    <div class="space-y-3">
                                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Click the microphone to record your pronunciation:</p>
                                        
                                        <div class="flex items-center space-x-3">
                                            <button type="button" 
                                                    @click="recordPronunciation(question.id)"
                                                    :disabled="isRecording"
                                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                                <i :class="isRecording ? 'fas fa-stop' : 'fas fa-microphone'" class="text-lg"></i>
                                                <span x-text="isRecording ? 'Recording...' : 'Record'"></span>
                                            </button>
                                            
                                            <!-- Recording status indicator -->
                                            <div x-show="isRecording" class="flex items-center space-x-2 text-blue-600 dark:text-blue-400">
                                                <div class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                                                <span class="text-sm font-medium">Recording...</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Audio playback -->
                                        <div x-show="answers[question.id]" class="mt-4">
                                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">Your recording:</p>
                                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg border border-blue-200 dark:border-blue-700">
                                                <audio controls class="w-full">
                                                    <source :src="typeof answers[question.id] === 'object' ? answers[question.id].audioUrl : answers[question.id]" type="audio/webm">
                                                </audio>
                                                
                                                <!-- Client-side recognition feedback -->
                                                <div x-show="answers[question.id] && typeof answers[question.id] === 'object' && answers[question.id].recognitionResult" 
                                                     class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-700">
                                                    <p class="text-xs text-green-700 dark:text-green-300">
                                                        <strong>Recognized:</strong> <span x-text="answers[question.id].recognitionResult.recognizedText"></span>
                                                        <span x-show="answers[question.id].recognitionResult.confidence" 
                                                              class="text-gray-500">(Confidence: <span x-text="Math.round(answers[question.id].recognitionResult.confidence * 100)"></span>%)</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>



                        <!-- Sentence Translation -->
                        <template x-if="question.type === 'sentence_translation'">
                            <div class="mt-2">
                                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg mb-4">
                                    <!-- Display question text if available -->
                                    <div x-show="question.text" class="mb-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-green-200 dark:border-green-700">
                                        <p class="text-sm text-green-700 dark:text-green-300 mb-2">Question:</p>
                                        <p class="text-lg font-medium text-gray-900 dark:text-white" x-text="question.text"></p>
                                    </div>
                                    
                                    <p class="text-sm text-green-700 dark:text-green-300 mb-3">Translate the following sentences:</p>
                                    
                                    <template x-for="(pair, index) in question.translation_pairs" :key="index">
                                        <div class="mb-4 p-3 bg-white dark:bg-dark-surface rounded border border-gray-200 dark:border-dark-border">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-medium text-gray-800 dark:text-gray-200" x-text="pair.japanese"></span>
                                                <span class="text-gray-400">→</span>
                                            </div>
                                            <input type="text" 
                                                   :name="'question_' + question.id + '_' + index"
                                                   x-model="answers[question.id + '_' + index]"
                                                   @input="onAnswerChange()"
                                                   class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-brand-red focus:border-brand-red"
                                                   :placeholder="'Enter English translation for ' + pair.japanese">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- Submit Button -->
        <div class="mt-8 flex justify-end">
            <button @click="testDialog()"
                    :disabled="!isComplete"
                    class="px-6 py-3 bg-brand-red text-white rounded-lg font-medium shadow-lg hover:bg-brand-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Submit Quiz
            </button>
        </div>
    </div>

    <!-- Results -->
    <div x-show="submitted && !error" class="space-y-6">
        <div class="text-center p-6 bg-gray-50 dark:bg-dark-border rounded-lg">
            <template x-if="results">
                <div>
                    <h4 class="text-2xl font-bold mb-4" x-text="'Score: ' + results.score + '/' + results.total"></h4>
                    <p class="text-lg text-gray-600 dark:text-gray-400" x-text="'Percentage: ' + Math.round(results.score/results.total * 100) + '%'"></p>
                </div>
            </template>
        </div>
        
        <!-- Question Review -->
        <div class="space-y-6">
            <template x-for="(question, index) in results.questions" :key="question.id">
                <div class="p-4 bg-gray-50 dark:bg-dark-border rounded-lg">
                    <div class="flex items-start">
                        <span class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full"
                              :class="question.correct ? 'bg-green-500' : 'bg-red-500'"
                              x-text="index + 1"></span>
                        <div class="ml-4 flex-grow">
                            <p class="text-lg font-medium text-gray-900 dark:text-white mb-2" x-text="question.text"></p>
                            <!-- Pronunciation-specific feedback -->
                            <template x-if="question.pronunciation_score !== undefined">
                                <div class="mb-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Pronunciation Analysis:</p>
                                    <div class="space-y-2">
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>Expected:</strong> <span x-text="question.expected_word || 'N/A'"></span>
                                            <span x-show="question.expected_romaji" class="text-gray-500"> (<span x-text="question.expected_romaji"></span>)</span>
                                        </p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>Meaning:</strong> <span x-text="question.expected_meaning || 'N/A'"></span>
                                        </p>
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>Accuracy Score:</strong> 
                                            <span :class="question.pronunciation_score >= (question.accuracy_threshold || 70) ? 'text-green-600 font-bold' : 'text-orange-600 font-bold'" 
                                                  x-text="Math.round(question.pronunciation_score) + '%'"></span>
                                            <span class="text-gray-500">(Threshold: <span x-text="question.accuracy_threshold || 70"></span>%)</span>
                                        </p>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="h-2 rounded-full transition-all duration-300" 
                                                 :class="question.pronunciation_score >= (question.accuracy_threshold || 70) ? 'bg-green-500' : 'bg-orange-500'"
                                                 :style="'width: ' + Math.min(100, Math.max(0, question.pronunciation_score)) + '%'"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            
                            <!-- Standard answer display for non-pronunciation questions -->
                            <template x-if="question.pronunciation_score === undefined">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Your answer: <span x-text="question.user_answer || 'No answer provided'"></span>
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Correct answer: <span x-text="question.correct_answer || 'N/A'"></span>
                                    </p>
                                </div>
                            </template>
                            
                            <p class="text-sm font-medium" :class="question.correct ? 'text-green-600' : 'text-red-600'">
                                Points: <span x-text="question.correct ? (question.points || question.score || 1) : 0"></span>/<span x-text="question.points || question.score || 1"></span>
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="text-center">
            <button @click="showRetakeDialog()"
                    :disabled="!canRetake"
                    class="px-6 py-3 bg-gray-200 dark:bg-dark-border text-gray-700 dark:text-white rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-dark-surface transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="canRetake ? 'Try Again' : 'No More Retakes'"></span>
            </button>
        </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div x-show="showSubmitDialog" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="showSubmitDialog = false"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Submit Quiz
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Are you sure you want to submit your quiz? You have answered 
                                    <span x-text="getAnsweredCount()"></span> out of 
                                    <span x-text="pagination?.total_questions || 0"></span> questions.
                                </p>
                                <div class="mt-3">
                                    <div class="bg-gray-200 dark:bg-dark-border rounded-full h-2">
                                        <div class="bg-brand-red h-2 rounded-full transition-all duration-300" 
                                             :style="'width: ' + getProgressPercentage() + '%'"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Progress: <span x-text="Math.round(getProgressPercentage())"></span>%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="submitQuiz(); showSubmitDialog = false"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Submit Quiz
                    </button>
                    <button @click="showSubmitDialog = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Retake Confirmation Modal -->
    <div x-show="showRetakeConfirmation" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                 @click="cancelRetake()"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-dark-surface px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/20 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Retake Quiz
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="getRetakeMessage()">
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-dark-border px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="confirmRetake()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Yes, Retake Quiz
                    </button>
                    <button @click="cancelRetake()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-dark-border shadow-sm px-4 py-2 bg-white dark:bg-dark-surface text-base font-medium text-gray-700 dark:text-white hover:bg-gray-50 dark:hover:bg-dark-border focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div> 