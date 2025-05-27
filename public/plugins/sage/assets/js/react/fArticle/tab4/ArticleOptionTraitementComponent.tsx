// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../functions/translations";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import {
  FormContentInterface,
  FormInterface,
} from "../../../interface/InputInterface";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getSageMetadata } from "../../../functions/getMetadata";
import { FormCheckbox } from "../../component/form/FormCheckbox";
import { DividerText } from "../../component/DividerText";

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
              Dom: (
                <DividerText
                  textAlign="left"
                  text={<h2>{translations.words.billing}</h2>}
                />
              ),
            },
            {
              fields: ["arEscompte", "arPublie", "arSommeil"].map((name) => {
                return {
                  name: name,
                  DomField: FormCheckbox,
                  initValues: {
                    value: getSageMetadata(name, articleMeta) ?? "",
                  },
                };
              }),
            },
            {
              fields: ["arFactPoids", "arVteDebit", "arContremarque"].map(
                (name) => {
                  return {
                    name: name,
                    DomField: FormCheckbox,
                    initValues: {
                      value: getSageMetadata(name, articleMeta) ?? "",
                    },
                  };
                },
              ),
            },
            {
              props: {
                size: { xs: 12 },
              },
              Dom: (
                <DividerText
                  textAlign="left"
                  text={<h2>{translations.words.impression}</h2>}
                />
              ),
            },
            {
              fields: ["arNotImp", "arFactForfait", "arHorsStat"].map(
                (name) => {
                  return {
                    name: name,
                    DomField: FormCheckbox,
                    initValues: {
                      value: getSageMetadata(name, articleMeta) ?? "",
                    },
                  };
                },
              ),
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
