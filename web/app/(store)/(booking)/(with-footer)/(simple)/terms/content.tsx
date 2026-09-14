"use client";

import { DefaultResponse, Term } from "@/types/global";
import { useQuery } from "@tanstack/react-query";
import { infoService } from "@/services/info";
import { useSettings } from "@/hook/use-settings";

interface TermsContentProps {
  data?: DefaultResponse<Term>;
}

export const TermsContent = ({ data }: TermsContentProps) => {
  const { language } = useSettings();
  const { data: terms } = useQuery(
    ["terms", language?.locale],
    () => infoService.terms({ lang: language?.locale }),
    {
      initialData: data,
    }
  );

  return (
    <div className="xl:container px-4 py-7">
      <div className="max-w-3xl mx-auto">
        <h1 className="md:text-head text-xl font-semibold mb-6">
          {terms?.data?.translation?.title}
        </h1>
        <div
          // Left-aligned, not justified: on the web, justified text (without
          // real hyphenation) produces uneven word-spacing "rivers" that
          // read worse than a ragged right edge, especially at this column
          // width - so this is a deliberate choice, not an oversight.
          // Preflight zeroes default <p>/<h2> margins, so without these
          // arbitrary-variant rules every paragraph and heading rendered
          // back-to-back with no visual separation at all.
          className="text-base leading-relaxed [&_p]:mb-4 [&_p:last-child]:mb-0 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-8 [&_h2]:mb-3 [&_h2:first-child]:mt-0 [&_a]:underline [&_a]:font-medium [&_em]:text-gray-field"
          dangerouslySetInnerHTML={{ __html: terms?.data?.translation?.description || "" }}
        />
      </div>
    </div>
  );
};
