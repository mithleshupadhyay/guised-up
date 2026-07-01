export type FeedAuthor = {
  id: number;
  name: string;
  username: string;
};

export type FeedPost = {
  id: number;
  author: FeedAuthor;
  text: string;
  image_url: string | null;
  authenticity_score: number;
  relationship_score?: number;
  semantic_score?: number;
  time_decay_score?: number;
  feed_score?: number;
  created_at: string;
  time_ago: string;
};

export type PaginatedResponse<T> = {
  data: T[];
  links?: {
    first?: string | null;
    last?: string | null;
    prev?: string | null;
    next?: string | null;
  };
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};
