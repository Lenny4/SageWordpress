// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import {
  FormContentInterface,
  FormInterface,
} from "../../../../../interface/InputInterface";
import { DividerText } from "../../../DividerText";
import { FormSelect } from "../../FormSelect";
import { transformOptionsObject } from "../../../../../functions/form";
import { FormInput } from "../../FormInput";
import { FormContentComponent } from "../../FormContentComponent";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const isNew = !arRef;

export const ArticleLogistiqueComponent = React.forwardRef((props, ref) => {
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
            Dom: (
              <DividerText
                textAlign="left"
                text={<h2>{translations.words.features}</h2>}
              />
            ),
          },
          {
            fields: [
              {
                name: "arUnitePoids",
                DomField: FormSelect,
                readOnly: !isNew, // todo test can select when creation
                options: transformOptionsObject(
                  translations.fArticles.arUnitePoids.values,
                ),
                initValues: {
                  value: getSageMetadata("arUnitePoids", articleMeta) ?? "",
                },
              },
              {
                name: "arPoidsNet",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arPoidsNet", articleMeta) ?? "",
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arCodeBarre",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arCodeBarre", articleMeta) ?? "",
                },
              },
              {
                name: "arPoidsBrut",
                DomField: FormInput,
                type: "number",
                initValues: {
                  value: getSageMetadata("arPoidsBrut", articleMeta) ?? "",
                },
              },
            ],
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
});
