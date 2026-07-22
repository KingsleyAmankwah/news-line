import { normalizeArticleDetail, normalizeFeedResponse } from "@/lib/parse-feed";

describe("normalizeFeedResponse", () => {
  it("parses a well-formed payload", () => {
    const raw = {
      data: [
        {
          id: "uuid-1",
          type: "article",
          title: "Hello",
          path: "/articles/hello",
          summary: "A teaser.",
          promoted: true,
          sticky: false,
          readingTimeMinutes: 4,
          publishedAt: "2026-07-01T09:30:00+00:00",
          updatedAt: "2026-07-02T09:30:00+00:00",
          author: { id: "u-1", name: "Jane" },
          category: { id: "c-1", name: "Engineering", slug: "engineering" },
          tags: [{ id: "t-1", name: "Drupal", slug: "drupal" }],
          hero: {
            alt: "Alt",
            width: 1200,
            height: 675,
            src: "https://cms.example/styles/feed_hero/x.webp",
            thumbnail: "https://cms.example/styles/feed_thumbnail/x.webp",
          },
        },
      ],
      meta: { count: 1, page: 0, itemsPerPage: 10, totalPages: 1 },
      links: { self: "/api/article-feed?page=0", next: null, prev: null },
    };

    const result = normalizeFeedResponse(raw);

    expect(result.data).toHaveLength(1);
    expect(result.data[0].title).toBe("Hello");
    expect(result.data[0].promoted).toBe(true);
    expect(result.data[0].category?.slug).toBe("engineering");
    expect(result.data[0].hero?.src).toContain("feed_hero");
    expect(result.meta.count).toBe(1);
    expect(result.links.self).toBe("/api/article-feed?page=0");
  });

  it("drops articles missing an id or title", () => {
    const raw = {
      data: [
        { id: "", title: "No id" },
        { id: "uuid-2", title: "" },
        { id: "uuid-3", title: "Keep me" },
      ],
      meta: { count: 3 },
    };

    const result = normalizeFeedResponse(raw);

    expect(result.data).toHaveLength(1);
    expect(result.data[0].id).toBe("uuid-3");
  });

  it("returns a well-formed empty feed for garbage input", () => {
    for (const garbage of [null, undefined, "nope", 42, [], { data: "not-array" }]) {
      const result = normalizeFeedResponse(garbage);
      expect(result.data).toEqual([]);
      expect(result.meta.itemsPerPage).toBeGreaterThanOrEqual(1);
      expect(result.links.next).toBeNull();
    }
  });

  it("degrades a hero with no src to null and defaults reading time", () => {
    const raw = {
      data: [
        {
          id: "uuid-4",
          title: "Sparse",
          hero: { alt: "no src here" },
          readingTimeMinutes: "not-a-number",
        },
      ],
    };

    const result = normalizeFeedResponse(raw);

    expect(result.data[0].hero).toBeNull();
    expect(result.data[0].readingTimeMinutes).toBe(1);
    expect(result.data[0].tags).toEqual([]);
  });

  it("keeps only valid tags", () => {
    const raw = {
      data: [
        {
          id: "uuid-5",
          title: "Tagged",
          tags: [
            { id: "t-1", name: "Valid", slug: "valid" },
            { id: "", name: "No id" },
            "garbage",
            null,
          ],
        },
      ],
    };

    const result = normalizeFeedResponse(raw);

    expect(result.data[0].tags).toHaveLength(1);
    expect(result.data[0].tags[0].name).toBe("Valid");
  });
});

describe("normalizeArticleDetail", () => {
  it("returns an article with its body", () => {
    const detail = normalizeArticleDetail({
      id: "uuid-1",
      title: "Full",
      body: "<p>Body HTML</p>",
    });

    expect(detail).not.toBeNull();
    expect(detail?.title).toBe("Full");
    expect(detail?.body).toBe("<p>Body HTML</p>");
  });

  it("defaults a missing body to an empty string", () => {
    const detail = normalizeArticleDetail({ id: "uuid-2", title: "No body" });
    expect(detail?.body).toBe("");
  });

  it("returns null for an unusable article", () => {
    expect(normalizeArticleDetail({ id: "", title: "" })).toBeNull();
    expect(normalizeArticleDetail(null)).toBeNull();
  });
});
