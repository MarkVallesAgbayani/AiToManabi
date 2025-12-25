# Pronunciation Check Setup Guide

This guide explains how to set up the pronunciation checking feature with Web Speech API.

## Overview

The pronunciation check feature uses **Web Speech API** to evaluate student pronunciation of Japanese words. It provides:

- Real-time speech recognition using browser's built-in Web Speech API
- Accuracy-based scoring
- Partial credit for close pronunciations
- Detailed feedback on pronunciation accuracy
- **No API key required** - completely free!

## Setup Instructions

### Web Speech API (No Setup Required!)

The Web Speech API is built into modern browsers and requires **no setup**:

- ✅ **Chrome**: Full support
- ✅ **Edge**: Full support  
- ✅ **Safari**: Limited support
- ❌ **Firefox**: Not supported

**That's it!** The system will automatically use Web Speech API for pronunciation recognition.

### Testing

1. Create a pronunciation question in your quiz
2. Set the Japanese word, romaji, and meaning
3. Set the accuracy threshold (default: 70%)
4. Test the recording functionality

## Features

### Scoring System

- **Full Points**: Pronunciation accuracy >= threshold (default 70%)
- **Partial Points**: Pronunciation accuracy >= 50% but < threshold
- **No Points**: Pronunciation accuracy < 50%

### Accuracy Calculation

The system compares the recognized text with the expected word using:
- Exact match (100% accuracy)
- Substring match (95% accuracy)
- Romaji comparison (90% accuracy)
- Levenshtein distance similarity
- Japanese-specific pattern recognition

### Client-Side Recognition

The system performs client-side speech recognition using the Web Speech API for immediate feedback and final scoring.

## Troubleshooting

### Common Issues

1. **"Microphone access denied"**
   - Check browser permissions
   - Ensure microphone is connected and working
   - Try refreshing the page

2. **"Speech recognition not available"**
   - Use Chrome, Edge, or Safari browsers (Firefox not supported)
   - Ensure HTTPS is enabled (required for microphone access)
   - Check if Web Speech API is supported in your browser

3. **Low accuracy scores**
   - Ensure students speak clearly and at normal pace
   - Check that the expected word is correctly set
   - Consider adjusting the accuracy threshold
   - Try using Chrome for best Web Speech API performance

### Debug Information

Check the browser console and server logs for detailed error messages and recognition results.

## Fallback Behavior

If the Web Speech API is not available or fails:
- The system uses a fallback evaluation method
- Students still receive a default score (75%) for recorded audio
- The system logs the fallback usage for debugging

## Customization

You can customize the pronunciation evaluation by modifying:
- `dashboard/config/speech_api.php` - Web Speech API configuration
- `dashboard/submit_quiz.php` - Scoring logic
- `dashboard/components/quiz.php` - UI display
- `dashboard/js/quiz.js` - Client-side functionality
