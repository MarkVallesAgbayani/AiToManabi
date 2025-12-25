-- Add video_copyright column to chapters table
-- This column will store copyright notices, attributions, or disclaimers for video content

ALTER TABLE chapters 
ADD COLUMN video_copyright TEXT NULL AFTER video_url;

-- Add index for better performance when filtering by content type
CREATE INDEX idx_chapters_content_type ON chapters(content_type);
