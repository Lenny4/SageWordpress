// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FormInterface } from "../../../../../interface/InputInterface";
import { DividerText } from "../../../DividerText";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormCheckbox } from "../../fields/FormCheckbox";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

export const ArticleOptionTraitementComponent = React.forwardRef(
  (props, ref) => {
    const getForm = (): FormInterface => {
      return {
        content: createFormContent({
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
                    value: getSageMetadata(name, articleMeta),
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
                      value: getSageMetadata(name, articleMeta),
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
                      value: getSageMetadata(name, articleMeta),
                    },
                  };
                },
              ),
            },
          ],
        }),
      };
    };

    const [form] = React.useState<FormInterface>(getForm());

    useImperativeHandle(ref, () => ({
      async isValid(): Promise<boolean> {
        return await handleFormIsValid(form.content);
      },
    }));

    return (
      <FormContentComponent content={form.content} transPrefix="fArticles" />
    );
  },
);
