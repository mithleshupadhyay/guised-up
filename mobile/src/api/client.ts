import { FeedPost, PaginatedResponse } from '../types/feed';

const API_URL = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api';
const AUTH_TOKEN = process.env.EXPO_PUBLIC_AUTH_TOKEN ?? '';

type RequestOptions = {
  method?: 'GET' | 'POST';
  body?: Record<string, unknown>;
};

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(AUTH_TOKEN ? { Authorization: `Bearer ${AUTH_TOKEN}` } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  if (!response.ok) {
    const body = await response.text();
    throw new Error(body || `Request failed with status ${response.status}`);
  }

  return response.json() as Promise<T>;
}

export function fetchFeed(page: number): Promise<PaginatedResponse<FeedPost>> {
  return request<PaginatedResponse<FeedPost>>(`/feed?page=${page}`);
}

export function searchPosts(query: string): Promise<PaginatedResponse<FeedPost>> {
  return request<PaginatedResponse<FeedPost>>(`/search?q=${encodeURIComponent(query)}`);
}

export function reactToPost(postId: number): Promise<void> {
  return request<void>('/interactions', {
    method: 'POST',
    body: {
      post_id: postId,
      type: 'reaction',
      metadata: {
        reaction: 'heart',
        source: 'feed_screen',
      },
    },
  });
}
