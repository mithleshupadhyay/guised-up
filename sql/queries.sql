-- D1. Top 10 most active users in the last 7 days, ranked by total interactions.
SELECT
    users.id AS user_id,
    users.name,
    users.email,
    COUNT(interactions.id) AS total_interactions,
    SUM(CASE WHEN interactions.type = 'view' THEN 1 ELSE 0 END) AS view_count,
    SUM(CASE WHEN interactions.type = 'reply' THEN 1 ELSE 0 END) AS reply_count,
    SUM(CASE WHEN interactions.type = 'reaction' THEN 1 ELSE 0 END) AS reaction_count
FROM users
JOIN interactions ON interactions.actor_id = users.id
WHERE interactions.created_at >= NOW() - INTERVAL '7 days'
GROUP BY users.id, users.name, users.email
ORDER BY total_interactions DESC, users.id ASC
LIMIT 10;

-- D2. For a given user_id, return posts from users they interact with most.
-- Bind :user_id from the application layer.
WITH relationship_depth AS (
    SELECT
        interactions.target_author_id AS author_id,
        COUNT(*) AS interaction_count,
        SUM(interactions.weight) AS weighted_interaction_score
    FROM interactions
    WHERE interactions.actor_id = :user_id
      AND interactions.target_author_id <> :user_id
    GROUP BY interactions.target_author_id
)
SELECT
    posts.id AS post_id,
    posts.author_id,
    users.name AS author_name,
    posts.body,
    posts.created_at,
    relationship_depth.interaction_count,
    relationship_depth.weighted_interaction_score
FROM relationship_depth
JOIN posts ON posts.author_id = relationship_depth.author_id
JOIN users ON users.id = posts.author_id
WHERE posts.created_at >= NOW() - INTERVAL '30 days'
ORDER BY
    relationship_depth.weighted_interaction_score DESC,
    relationship_depth.interaction_count DESC,
    posts.created_at DESC
LIMIT 100;

-- D3. Posts viewed more than 100 times but with zero reactions.
WITH post_interaction_counts AS (
    SELECT
        posts.id AS post_id,
        posts.author_id,
        posts.created_at,
        COUNT(interactions.id) FILTER (WHERE interactions.type = 'view') AS view_count,
        COUNT(interactions.id) FILTER (WHERE interactions.type = 'reaction') AS reaction_count
    FROM posts
    LEFT JOIN interactions ON interactions.post_id = posts.id
    GROUP BY posts.id, posts.author_id, posts.created_at
)
SELECT
    post_id,
    author_id,
    view_count,
    created_at
FROM post_interaction_counts
WHERE view_count > 100
  AND reaction_count = 0
ORDER BY view_count DESC, created_at DESC;

-- D4. Potential spam: users who created more than 20 posts in the last 24 hours.
SELECT
    users.id AS user_id,
    users.email,
    COUNT(posts.id) AS post_count,
    MIN(posts.created_at) AS first_post_at,
    MAX(posts.created_at) AS latest_post_at
FROM users
JOIN posts ON posts.author_id = users.id
WHERE posts.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY users.id, users.email
HAVING COUNT(posts.id) > 20
ORDER BY post_count DESC, latest_post_at DESC;
