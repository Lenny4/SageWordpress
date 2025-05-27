// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../functions/translations";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import {
  FormContentInterface,
  FormInterface,
} from "../../../interface/InputInterface";
import { FormContentComponent } from "../../component/form/FormContentComponent";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleOptionTraitementComponent = React.forwardRef(
  (props, ref) => {
    const getForm = (): FormInterface => {
      const formContent: FormContentInterface[] = [
        {
          props: {
            container: true,
            spacing: 1,
            sx: { p: 1 },
          },
          children: [
            {
              props: {
                size: { xs: 12 },
              },
            },
          ],
        },
      ];
      return {
        content: formContent,
      };
    };

    const [form, setForm] = React.useState<FormInterface>(getForm());

    useImperativeHandle(ref, () => ({
      getForm() {
        return form;
      },
    }));

    return (
      <FormContentComponent content={form.content} transPrefix="fArticles" />
    );
  },
);
