"use client";

import { DefaultResponse, Term } from "@/types/global";
import { useQuery } from "@tanstack/react-query";
import { infoService } from "@/services/info";
import { useSettings } from "@/hook/use-settings";

interface PrivacyContentProps {
  data?: DefaultResponse<Term>;
}

export const PrivacyContent = ({ data }: PrivacyContentProps) => {
  const { language } = useSettings();
  const { data: privacy } = useQuery(
    ["privacy", language?.locale],
    () => infoService.privacy({ lang: language?.locale }),
    {
      initialData: data,
    }
  );
  return (
    <div className="xl:container px-4 py-7">
      <div className="max-w-3xl mx-auto">
        <h1 className="md:text-head text-xl font-semibold mb-6">
          {privacy?.data?.translation?.title}
        </h1>
        <div
          // See terms/content.tsx for why left-aligned (not justified) and
          // why these arbitrary-variant rules are needed at all - same
          // preflight-vs-dangerouslySetInnerHTML spacing gap applies here.
          className="text-base leading-relaxed [&_p]:mb-4 [&_p:last-child]:mb-0 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:mt-8 [&_h2]:mb-3 [&_h2:first-child]:mt-0 [&_a]:underline [&_a]:font-medium [&_em]:text-gray-field"
          dangerouslySetInnerHTML={{ __html: privacy?.data?.translation?.description || "" }}
        />
      </div>
    </div>
  );
};
