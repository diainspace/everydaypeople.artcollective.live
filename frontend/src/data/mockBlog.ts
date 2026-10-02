export type MockPost = {
  id: string
  number: string
  title: string
  slug: string
  category: string
  date: string
  description: string
  artClass: string
  artAlt: string
  content: Array<{ heading?: string; text: string }>
}

export const mockPosts: MockPost[] = [
  {
    id: 'post-001', number: '01', title: 'The Work Before the Words',
    slug: 'the-work-before-the-words', category: 'Notes', date: 'September 24, 2026',
    description: 'What changes when we let the image arrive before we explain it?',
    artClass: 'art-pressure', artAlt: 'Abstract study in black, cream, cobalt, and acid yellow.',
    content: [
      { text: 'There is a point in the process when naming a thing too quickly makes it smaller. The work needs room to arrive before it is asked to account for itself.' },
      { heading: 'Looking without solving', text: 'We are practicing a slower kind of attention: noticing pressure, rhythm, interruption, and scale without forcing the image into a clean explanation.' },
      { text: 'The result can stay unresolved. It can be direct without becoming simple. It can hold contradiction and still communicate.' },
    ],
  },
  {
    id: 'post-002', number: '02', title: 'Five Artists, One Unfinished Question',
    slug: 'five-artists-one-unfinished-question', category: 'Collective', date: 'September 17, 2026',
    description: 'A shared prompt moved through five studios and returned with five different answers.',
    artClass: 'art-voices', artAlt: 'Layered red, violet, black, and white abstract forms.',
    content: [{ text: 'The same question does not produce the same work. That difference is the point.' }],
  },
  {
    id: 'post-003', number: '03', title: 'Making Space for the Strange Part',
    slug: 'making-space-for-the-strange-part', category: 'Process', date: 'September 8, 2026',
    description: 'The awkward mark is often the one holding the whole piece open.',
    artClass: 'art-strange', artAlt: 'Gestural orange figure against blue and pale pink fields.',
    content: [{ text: 'Polish can clarify, but it can also erase the evidence that something happened.' }],
  },
  {
    id: 'post-004', number: '04', title: 'Open Wall, Open Conversation',
    slug: 'open-wall-open-conversation', category: 'Events', date: 'August 29, 2026',
    description: 'Notes from an evening of work, conversation, and unfinished ideas.',
    artClass: 'art-wall', artAlt: 'Graphic black forms crossing a warm white and green field.',
    content: [{ text: 'The room changed as people spoke. Each response became part of the installation.' }],
  },
]

export const featuredPost = mockPosts[0]
