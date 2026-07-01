import { Heart, RefreshCcw, Search, X } from 'lucide-react-native';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Keyboard,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { fetchFeed, reactToPost, searchPosts } from '../api/client';
import { FeedPost } from '../types/feed';

const SEARCH_DEBOUNCE_MS = 350;

export function FeedScreen() {
  const [posts, setPosts] = useState<FeedPost[]>([]);
  const [searchResults, setSearchResults] = useState<FeedPost[]>([]);
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [initialLoading, setInitialLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [searching, setSearching] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reactingPostIds, setReactingPostIds] = useState<Set<number>>(new Set());
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const searchMode = query.trim().length >= 2;
  const visiblePosts = searchMode ? searchResults : posts;

  const loadFeed = useCallback(async (nextPage: number, replace = false) => {
    if (nextPage > lastPage && !replace) {
      return;
    }

    replace ? setRefreshing(true) : setLoadingMore(nextPage > 1);
    setError(null);

    try {
      const response = await fetchFeed(nextPage);
      setPosts((current) => (replace ? response.data : [...current, ...response.data]));
      setPage(response.meta?.current_page ?? nextPage);
      setLastPage(response.meta?.last_page ?? nextPage);
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Unable to load feed.');
    } finally {
      setInitialLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  }, [lastPage]);

  useEffect(() => {
    loadFeed(1, true);
  }, []);

  useEffect(() => {
    if (searchTimer.current) {
      clearTimeout(searchTimer.current);
    }

    const trimmed = query.trim();

    if (trimmed.length < 2) {
      setSearchResults([]);
      setSearching(false);
      return;
    }

    setSearching(true);
    searchTimer.current = setTimeout(async () => {
      try {
        const response = await searchPosts(trimmed);
        setSearchResults(response.data);
        setError(null);
      } catch (exception) {
        setError(exception instanceof Error ? exception.message : 'Unable to search posts.');
      } finally {
        setSearching(false);
      }
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimer.current) {
        clearTimeout(searchTimer.current);
      }
    };
  }, [query]);

  const onEndReached = useCallback(() => {
    if (searchMode || loadingMore || initialLoading || page >= lastPage) {
      return;
    }

    loadFeed(page + 1);
  }, [initialLoading, lastPage, loadFeed, loadingMore, page, searchMode]);

  const onRefresh = useCallback(() => {
    Keyboard.dismiss();
    setQuery('');
    loadFeed(1, true);
  }, [loadFeed]);

  const onReact = useCallback(async (postId: number) => {
    setReactingPostIds((current) => new Set(current).add(postId));

    try {
      await reactToPost(postId);
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Unable to log reaction.');
    } finally {
      setReactingPostIds((current) => {
        const next = new Set(current);
        next.delete(postId);
        return next;
      });
    }
  }, []);

  const headerLabel = useMemo(() => {
    if (searchMode) {
      return `${searchResults.length} semantic matches`;
    }

    return 'Real Connections';
  }, [searchMode, searchResults.length]);

  if (initialLoading) {
    return (
      <View style={styles.centerState}>
        <ActivityIndicator color="#245c4f" />
        <Text style={styles.stateTitle}>Loading feed</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <View>
          <Text style={styles.eyebrow}>Guised Up</Text>
          <Text style={styles.title}>{headerLabel}</Text>
        </View>
        <Pressable accessibilityLabel="Refresh feed" onPress={onRefresh} style={styles.iconButton}>
          <RefreshCcw size={20} color="#245c4f" />
        </Pressable>
      </View>

      <View style={styles.searchRow}>
        <Search size={18} color="#6f6a5f" />
        <TextInput
          value={query}
          onChangeText={setQuery}
          placeholder="Search honest posts, travel stories, last week..."
          placeholderTextColor="#8a8579"
          style={styles.searchInput}
          returnKeyType="search"
          autoCapitalize="none"
        />
        {query.length > 0 ? (
          <Pressable accessibilityLabel="Clear search" onPress={() => setQuery('')} style={styles.clearButton}>
            <X size={17} color="#6f6a5f" />
          </Pressable>
        ) : null}
      </View>

      {error ? (
        <View style={styles.errorBanner}>
          <Text style={styles.errorText} numberOfLines={3}>{error}</Text>
        </View>
      ) : null}

      {searching ? (
        <View style={styles.inlineLoading}>
          <ActivityIndicator color="#245c4f" />
          <Text style={styles.inlineLoadingText}>Searching semantically</Text>
        </View>
      ) : null}

      <FlatList
        data={visiblePosts}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <PostCard
            post={item}
            reacting={reactingPostIds.has(item.id)}
            onReact={() => onReact(item.id)}
          />
        )}
        contentContainerStyle={visiblePosts.length === 0 ? styles.emptyList : styles.list}
        onEndReached={onEndReached}
        onEndReachedThreshold={0.55}
        refreshing={refreshing}
        onRefresh={onRefresh}
        keyboardShouldPersistTaps="handled"
        ListEmptyComponent={<EmptyState searchMode={searchMode} />}
        ListFooterComponent={loadingMore ? <ActivityIndicator style={styles.footerLoader} color="#245c4f" /> : null}
      />
    </View>
  );
}

type PostCardProps = {
  post: FeedPost;
  reacting: boolean;
  onReact: () => void;
};

function PostCard({ post, reacting, onReact }: PostCardProps) {
  const initials = post.author.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  return (
    <View style={styles.card}>
      <View style={styles.cardHeader}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <View style={styles.authorBlock}>
          <Text style={styles.authorName} numberOfLines={1}>{post.author.name}</Text>
          <Text style={styles.authorMeta} numberOfLines={1}>@{post.author.username} · {post.time_ago}</Text>
        </View>
        <ScorePill score={post.feed_score ?? post.semantic_score ?? post.authenticity_score} />
      </View>

      <Text style={styles.postText}>{post.text}</Text>

      <View style={styles.cardFooter}>
        <Text style={styles.signalText}>
          Authenticity {(post.authenticity_score * 100).toFixed(0)}%
        </Text>
        <Pressable
          accessibilityLabel="React to post"
          disabled={reacting}
          onPress={onReact}
          style={({ pressed }) => [
            styles.reactionButton,
            pressed || reacting ? styles.reactionButtonPressed : null,
          ]}
        >
          {reacting ? (
            <ActivityIndicator color="#9f3347" size="small" />
          ) : (
            <Heart size={18} color="#9f3347" />
          )}
          <Text style={styles.reactionText}>React</Text>
        </Pressable>
      </View>
    </View>
  );
}

function ScorePill({ score }: { score: number }) {
  return (
    <View style={styles.scorePill}>
      <Text style={styles.scoreText}>{Math.round(score * 100)}</Text>
    </View>
  );
}

function EmptyState({ searchMode }: { searchMode: boolean }) {
  return (
    <View style={styles.emptyState}>
      <Text style={styles.stateTitle}>{searchMode ? 'No semantic matches' : 'No posts yet'}</Text>
      <Text style={styles.stateCopy}>
        {searchMode ? 'Try a broader natural-language search.' : 'Real posts will appear here once people share.'}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f3f7f6',
  },
  header: {
    paddingHorizontal: 18,
    paddingTop: 18,
    paddingBottom: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  eyebrow: {
    color: '#7a4f5d',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  title: {
    marginTop: 3,
    color: '#1f2a25',
    fontSize: 26,
    fontWeight: '800',
    letterSpacing: 0,
  },
  iconButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e4f0ec',
    borderWidth: 1,
    borderColor: '#bfd5ce',
  },
  searchRow: {
    marginHorizontal: 18,
    marginBottom: 10,
    minHeight: 48,
    borderRadius: 8,
    paddingHorizontal: 13,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#d5e0dd',
  },
  searchInput: {
    flex: 1,
    color: '#1f2a25',
    fontSize: 15,
    lineHeight: 20,
  },
  clearButton: {
    width: 30,
    height: 30,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e7eeec',
  },
  list: {
    paddingHorizontal: 18,
    paddingBottom: 28,
    gap: 12,
  },
  emptyList: {
    flexGrow: 1,
    paddingHorizontal: 18,
    justifyContent: 'center',
  },
  card: {
    borderRadius: 8,
    padding: 15,
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#d9e3df',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
  },
  avatar: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: '#245c4f',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: '#ffffff',
    fontWeight: '800',
    fontSize: 14,
    letterSpacing: 0,
  },
  authorBlock: {
    flex: 1,
    minWidth: 0,
  },
  authorName: {
    color: '#1f2a25',
    fontWeight: '800',
    fontSize: 15,
    letterSpacing: 0,
  },
  authorMeta: {
    marginTop: 2,
    color: '#67746f',
    fontSize: 13,
    letterSpacing: 0,
  },
  scorePill: {
    minWidth: 38,
    height: 30,
    borderRadius: 15,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e4f0ec',
    borderWidth: 1,
    borderColor: '#bfd5ce',
  },
  scoreText: {
    color: '#245c4f',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0,
  },
  postText: {
    marginTop: 13,
    color: '#24302b',
    fontSize: 16,
    lineHeight: 23,
    letterSpacing: 0,
  },
  cardFooter: {
    marginTop: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  signalText: {
    flex: 1,
    color: '#67746f',
    fontSize: 13,
    letterSpacing: 0,
  },
  reactionButton: {
    minWidth: 96,
    height: 38,
    borderRadius: 19,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    backgroundColor: '#f9e7eb',
    borderWidth: 1,
    borderColor: '#e9c6ce',
  },
  reactionButtonPressed: {
    opacity: 0.7,
  },
  reactionText: {
    color: '#9f3347',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0,
  },
  centerState: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
    backgroundColor: '#f3f7f6',
  },
  inlineLoading: {
    marginHorizontal: 18,
    marginBottom: 10,
    minHeight: 38,
    borderRadius: 8,
    paddingHorizontal: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#e4f0ec',
  },
  inlineLoadingText: {
    color: '#245c4f',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
  },
  errorBanner: {
    marginHorizontal: 18,
    marginBottom: 10,
    borderRadius: 8,
    padding: 12,
    backgroundColor: '#fae4df',
    borderWidth: 1,
    borderColor: '#edb9ae',
  },
  errorText: {
    color: '#983728',
    fontSize: 13,
    lineHeight: 18,
    letterSpacing: 0,
  },
  emptyState: {
    alignItems: 'center',
    padding: 24,
  },
  stateTitle: {
    color: '#1f2a25',
    fontSize: 17,
    fontWeight: '800',
    letterSpacing: 0,
  },
  stateCopy: {
    marginTop: 7,
    color: '#67746f',
    fontSize: 14,
    lineHeight: 20,
    textAlign: 'center',
    letterSpacing: 0,
  },
  footerLoader: {
    paddingVertical: 18,
  },
});
