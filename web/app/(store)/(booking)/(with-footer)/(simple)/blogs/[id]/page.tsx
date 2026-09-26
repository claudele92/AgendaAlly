import React from "react";
import dayjs from "dayjs";
import fetcher from "@/lib/fetcher";
import { DefaultResponse } from "@/types/global";
import { Blog, BlogFullTranslation } from "@/types/blog";
import Image from "next/image";
import { buildUrlQueryParams } from "@/utils/build-url-query-params";
import { cookies } from "next/headers";
import { notFound } from "next/navigation";
import dynamic from "next/dynamic";
import { Metadata } from "next";
import { BackButton } from "@/components/back-button";
import ReviewList from "@/app/(store)/(booking)/components/reviews/review-list";
import ReviewSummaryShort from "@/app/(store)/(booking)/components/reviews/review-summary-short";

const CreateReview = dynamic(() => import("../components/blog-review-create"));

export const generateMetadata = async (
  props: {
    params: Promise<{ id: string }>;
  }
): Promise<Metadata> => {
  const params = await props.params;
  const lang = (await cookies()).get("lang")?.value || "en";
  // redirectOnError already turns a genuine 404 response into notFound(),
  // but that only helps once fetch() actually gets a response back - a
  // network-level failure (backend unreachable) throws before ever
  // reaching that check, so this needs its own try/catch to close that gap.
  let blog;
  try {
    blog = await fetcher<DefaultResponse<Blog<BlogFullTranslation>>>(
      buildUrlQueryParams(`v1/rest/blog-by-id/${params.id}`, { lang }),
      {
        redirectOnError: true,
      }
    );
  } catch {
    notFound();
  }
  return {
    title: blog.data.translation?.title,
    description: blog.data.translation?.short_desc,
    openGraph: {
      title: blog.data.translation?.title,
      description: blog.data.translation?.short_desc,
      images: [
        {
          url: blog.data.img,
        },
      ],
    },
  };
};

const BlogDetailPage = async (props: { params: Promise<{ id: string }> }) => {
  const params = await props.params;
  const lang = (await cookies()).get("lang")?.value || "en";
  let blog;
  try {
    blog = await fetcher<DefaultResponse<Blog<BlogFullTranslation>>>(
      buildUrlQueryParams(`v1/rest/blog-by-id/${params.id}`, { lang }),
      { redirectOnError: true }
    );
  } catch {
    notFound();
  }
  return (
    <section className="xl:container px-4 my-7">
      <BackButton title="blog" />
      <div className="text-sm text-gray-bold mt-7">
        {dayjs(blog?.data?.published_at, "YYYY-MM-DD").format("dd MMM, YYYY")}
      </div>
      <h3 className="font-bold md:text-3xl text-2xl">{blog?.data.translation?.title}</h3>
      <div className="relative rounded-3xl overflow-hidden lg:aspect-[3/1] md:aspect-[2/1] aspect-square my-5">
        <Image
          src={blog?.data?.img}
          alt={blog?.data.translation?.title || "blog"}
          className="object-cover"
          fill
        />
      </div>
      <div className="grid grid-cols-7 gap-7 my-7">
        <div className="xl:col-span-5 lg:col-span-4 col-span-7">
          <div
            dangerouslySetInnerHTML={{ __html: blog?.data.translation?.description || "" }}
            // Same treatment as terms/privacy's content div: Preflight
            // zeroes default <p>/<h2> margins, so without these
            // arbitrary-variant rules every paragraph and heading rendered
            // back-to-back with no visual separation at all. Left-aligned,
            // not justified, for the same reason as there - justified text
            // without real hyphenation reads worse than ragged-right at
            // this column width.
            className="text-base leading-relaxed [&_p]:mb-4 [&_p:last-child]:mb-0 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-8 [&_h2]:mb-3 [&_h2:first-child]:mt-0 [&_a]:underline [&_a]:font-medium [&_em]:text-gray-field"
          />
          <div className="mt-10">
            <ReviewList title="comments" type="blogs" id={params.id} />
          </div>
        </div>
        <div className="xl:col-span-2 lg:col-span-3 col-span-7">
          <div className="sticky top-5">
            <ReviewSummaryShort type="blogs" typeId={Number(params.id)} />
            <CreateReview id={Number(params.id)} />
          </div>
        </div>
      </div>
    </section>
  );
};

export default BlogDetailPage;
