import { useEffect, useState } from 'react'
import './App.css'

type Post = {
  id: string
  title: string
  date: string
  catName: string
  description: string
  content: string
}

type FeedState =
  | { status: 'loading' }
  | { status: 'error' }
  | { status: 'ready'; posts: Post[] }

function isPost(value: unknown): value is Post {
  if (!value || typeof value !== 'object') return false
  const post = value as Record<string, unknown>
  return ['id', 'title', 'date', 'catName', 'description', 'content'].every(
    (field) => typeof post[field] === 'string',
  ) && Number.isFinite(Date.parse(post.date as string))
}

const dateFormat = new Intl.DateTimeFormat('en-US', {
  dateStyle: 'long',
  timeZone: 'America/New_York',
})

// Initial text renderer supports paragraphs and Markdown headings.
// React escapes all text; authored HTML is never executed.
function PostContent({ content }: { content: string }) {
  return content.replace(/\r\n?/g, '\n').split(/\n\s*\n/).filter(Boolean).map((block, index) => {
    const heading = /^(#{1,6})\s+([^\n]+)\n?$/.exec(block)
    if (heading) {
      const text = heading[2]
      // Page title is h1, post titles are h2, body headings start at h3.
      switch (Math.min(heading[1].length + 2, 6)) {
        case 3: return <h3 key={index}>{text}</h3>
        case 4: return <h4 key={index}>{text}</h4>
        case 5: return <h5 key={index}>{text}</h5>
        default: return <h6 key={index}>{text}</h6>
      }
    }
    return <p key={index}>{block}</p>
  })
}

function App() {
  const [state, setState] = useState<FeedState>({ status: 'loading' })
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    let active = true
    const timeout = window.setTimeout(() => controller.abort(), 15000)

    async function loadPosts() {
      try {
        const response = await fetch('/api/posts.php', { signal: controller.signal })
        if (!response.ok) throw new Error('Unable to fetch posts')
        const data: unknown = await response.json()
        if (!data || typeof data !== 'object' || !('posts' in data)
          || !Array.isArray(data.posts) || !data.posts.every(isPost)) {
          throw new Error('Invalid posts response')
        }
        if (active) setState({ status: 'ready', posts: data.posts })
      } catch {
        if (active) setState({ status: 'error' })
      } finally {
        window.clearTimeout(timeout)
      }
    }

    void loadPosts()
    return () => {
      active = false
      window.clearTimeout(timeout)
      controller.abort()
    }
  }, [attempt])

  function retry() {
    setState({ status: 'loading' })
    setAttempt((value) => value + 1)
  }

  return (
    <main className="blog">
      <header className="site-header">
        <p className="eyebrow">Our blog</p>
        <h1>Everyday People Art Collective</h1>
        <p>Art, stories, and updates from our creative community.</p>
      </header>

      {state.status === 'loading' && <p role="status">Loading posts…</p>}
      {state.status === 'error' && (
        <div className="notice">
          <p role="alert">We couldn’t load the posts. Please try again.</p>
          <button type="button" onClick={retry}>Try again</button>
        </div>
      )}
      {state.status === 'ready' && state.posts.length === 0 && (
        <p role="status">No posts yet. Please check back soon.</p>
      )}
      {state.status === 'ready' && state.posts.map((post) => (
        <article className="post" key={post.id}>
          <header>
            <p className="post-meta">
              <time dateTime={post.date}>{dateFormat.format(new Date(post.date))}</time>
              {post.catName && <span> · {post.catName}</span>}
            </p>
            <h2>{post.title}</h2>
            <p className="description">{post.description}</p>
          </header>
          <div className="post-content"><PostContent content={post.content} /></div>
        </article>
      ))}
    </main>
  )
}

export default App
