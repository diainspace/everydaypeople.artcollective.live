import { PostCard } from '../components/PostCard'
import { featuredPost } from '../data/mockBlog'

export function HomePage() {
  return (
    <main>
      <section className="intro" aria-labelledby="intro-title">
        <p className="section-number">Issue / 001</p>
        <h1 id="intro-title">Art lives<br />with people.</h1>
        <div className="intro-note">
          <p>Art, stories, and updates.</p>
        </div>
      </section>

      <section aria-label="Featured post"><PostCard post={featuredPost} featured /></section>

      <section className="statement">
        <p className="section-number">What we believe</p>
        <p className="statement-copy"><a className="statement-link" href="/about/">The work does not need permission to be direct, unfinished, difficult, joyful, or strange. &gt;&gt;</a></p>
      </section>
    </main>
  )
}
