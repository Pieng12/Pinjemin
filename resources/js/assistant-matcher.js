export function normalizeQuestion(value) {
    return String(value).normalize('NFKC').toLocaleLowerCase('id-ID')
        .replace(/[^\p{L}\p{N}\s]/gu, ' ').replace(/\s+/g, ' ').trim();
}

function nearWord(left, right) {
    if (left === right) return true;
    if (Math.min(left.length, right.length) < 5 || Math.abs(left.length - right.length) > 1) return false;
    let i = 0;
    let j = 0;
    let differences = 0;
    while (i < left.length && j < right.length) {
        if (left[i] === right[j]) { i++; j++; continue; }
        if (++differences > 1) return false;
        if (left.length <= right.length) j++;
        if (left.length >= right.length) i++;
    }
    return differences + Number(i < left.length || j < right.length) <= 1;
}

export function matchQuestion(question, topics) {
    const query = normalizeQuestion(question);
    if (!query) return { kind: 'empty', topics: [] };
    const words = [...new Set(query.split(' '))];
    const ranked = topics.map(topic => {
        const aliases = [topic.question, ...topic.aliases].map(normalizeQuestion);
        const exact = aliases.includes(query);
        const phrases = aliases.filter(alias => alias.includes(' ') && (` ${query} `).includes(` ${alias} `));
        const single = aliases.some(alias => !alias.includes(' ') && words.includes(alias));
        const groups = topic.keywords.map(group => words.filter(word => group.some(term => nearWord(word, term))));
        const distinct = new Set(groups.flat()).size;
        const keywords = groups.every(group => group.length > 0) && distinct >= 2;
        return { topic, exact, score: exact ? 1000 : phrases.length ? 100 + Math.max(...phrases.map(phrase => phrase.length)) : single ? 80 : keywords ? 30 + distinct : 0 };
    }).filter(result => result.score > 0).sort((left, right) => right.score - left.score);

    const exact = ranked.filter(result => result.exact);
    if (exact.length === 1) return { kind: 'answer', topics: [exact[0].topic] };
    if (!ranked.length) return { kind: 'unknown', topics: [] };
    const strong = ranked.filter(result => result.score >= 80);
    const candidates = strong.length ? strong : ranked.filter(result => result.score >= ranked[0].score - 1);
    return { kind: candidates.length === 1 ? 'answer' : 'choices', topics: candidates.slice(0, 3).map(result => result.topic) };
}
