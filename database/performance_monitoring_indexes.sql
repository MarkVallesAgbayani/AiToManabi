-- =====================================================
-- PERFORMANCE MONITORING DATABASE INDEXES
-- Optimized for better query performance
-- =====================================================

-- =====================================================
-- PAGE PERFORMANCE LOG INDEXES
-- =====================================================

-- Primary performance index for date-based queries
CREATE INDEX idx_page_performance_created_at ON page_performance_log (created_at);

-- Index for page name filtering and grouping
CREATE INDEX idx_page_performance_page_name ON page_performance_log (page_name);

-- Index for status filtering (fast, slow, timeout, error)
CREATE INDEX idx_page_performance_status ON page_performance_log (status);

-- Index for user-based queries
CREATE INDEX idx_page_performance_user_id ON page_performance_log (user_id);

-- Index for load duration analysis
CREATE INDEX idx_page_performance_load_duration ON page_performance_log (load_duration);

-- Composite index for common dashboard queries
CREATE INDEX idx_page_performance_dashboard ON page_performance_log (created_at, page_name, status);

-- Composite index for user activity analysis
CREATE INDEX idx_page_performance_user_activity ON page_performance_log (user_id, created_at, page_name);

-- Index for performance statistics queries
CREATE INDEX idx_page_performance_stats ON page_performance_log (created_at, status, load_duration);

-- Index for chart data queries (page load times by page)
CREATE INDEX idx_page_performance_chart ON page_performance_log (page_name, created_at, load_duration);

-- =====================================================
-- SYSTEM UPTIME LOG INDEXES
-- =====================================================

-- Primary index for time-based queries
CREATE INDEX idx_system_uptime_start_time ON system_uptime_log (start_time);

-- Index for event type filtering (uptime, downtime)
CREATE INDEX idx_system_uptime_event_type ON system_uptime_log (event_type);

-- Index for status filtering (active, completed)
CREATE INDEX idx_system_uptime_status ON system_uptime_log (status);

-- Index for severity filtering
CREATE INDEX idx_system_uptime_severity ON system_uptime_log (severity);

-- Composite index for current status queries
CREATE INDEX idx_system_uptime_current ON system_uptime_log (status, event_type, start_time);

-- Composite index for uptime statistics
CREATE INDEX idx_system_uptime_stats ON system_uptime_log (start_time, event_type, status, duration_seconds);

-- Index for recent activities
CREATE INDEX idx_system_uptime_recent ON system_uptime_log (start_time, event_type, severity);

-- =====================================================
-- OPTIMIZED QUERIES WITH INDEXES
-- =====================================================

-- Example: Get performance statistics (uses idx_page_performance_stats)
-- SELECT 
--     COUNT(*) as total_requests,
--     AVG(load_duration) as avg_load_time,
--     COUNT(CASE WHEN status = 'fast' THEN 1 END) as fast_count
-- FROM page_performance_log 
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Example: Get uptime statistics (uses idx_system_uptime_stats)
-- SELECT 
--     SUM(CASE WHEN event_type = 'uptime' THEN duration_seconds END) as total_uptime,
--     SUM(CASE WHEN event_type = 'downtime' THEN duration_seconds END) as total_downtime
-- FROM system_uptime_log 
-- WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
--   AND status = 'completed';

-- Example: Get chart data (uses idx_page_performance_chart)
-- SELECT 
--     page_name,
--     AVG(load_duration) as avg_load_time
-- FROM page_performance_log 
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY page_name;

-- =====================================================
-- MAINTENANCE QUERIES
-- =====================================================

-- Clean up old performance logs (keep last 90 days)
-- DELETE FROM page_performance_log 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean up old uptime logs (keep last 90 days)
-- DELETE FROM system_uptime_log 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- =====================================================
-- PERFORMANCE ANALYSIS QUERIES
-- =====================================================

-- Find slowest pages
-- SELECT page_name, AVG(load_duration) as avg_load_time, COUNT(*) as request_count
-- FROM page_performance_log 
-- WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY page_name 
-- ORDER BY avg_load_time DESC 
-- LIMIT 10;

-- Find pages with most errors
-- SELECT page_name, COUNT(*) as error_count
-- FROM page_performance_log 
-- WHERE status IN ('timeout', 'error') 
--   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
-- GROUP BY page_name 
-- ORDER BY error_count DESC 
-- LIMIT 10;

-- System uptime analysis
-- SELECT 
--     DATE(start_time) as date,
--     SUM(CASE WHEN event_type = 'uptime' THEN duration_seconds END) as uptime_seconds,
--     SUM(CASE WHEN event_type = 'downtime' THEN duration_seconds END) as downtime_seconds
-- FROM system_uptime_log 
-- WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
--   AND status = 'completed'
-- GROUP BY DATE(start_time)
-- ORDER BY date;

-- =====================================================
-- INDEX USAGE MONITORING
-- =====================================================

-- Check index usage (MySQL 5.7+)
-- SELECT 
--     TABLE_NAME,
--     INDEX_NAME,
--     CARDINALITY,
--     SUB_PART,
--     NULLABLE
-- FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND TABLE_NAME IN ('page_performance_log', 'system_uptime_log')
-- ORDER BY TABLE_NAME, CARDINALITY DESC;

-- =====================================================
-- TABLE OPTIMIZATION
-- =====================================================

-- Analyze tables for better query planning
-- ANALYZE TABLE page_performance_log;
-- ANALYZE TABLE system_uptime_log;

-- Optimize tables (run during low traffic)
-- OPTIMIZE TABLE page_performance_log;
-- OPTIMIZE TABLE system_uptime_log;
