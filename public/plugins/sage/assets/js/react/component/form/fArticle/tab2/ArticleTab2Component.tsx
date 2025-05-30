// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import Grid from "@mui/material/Grid";
import { ArticleCataloguesComponent } from "./ArticleCataloguesComponent";
import { ArticleGlossairesComponent } from "./ArticleGlossairesComponent";
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
import { FormInput } from "../../fields/FormInput";
import { FormSelect } from "../../fields/FormSelect";
import { TOKEN } from "../../../../../token";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const fPays: any[] = JSON.parse(
  $(`[data-${TOKEN}-fpays]`).attr(`data-${TOKEN}-fpays`) ?? "[]",
);

export const ArticleTab2Component = React.forwardRef((props, ref) => {
  const [form] = React.useState<FormInterface>(() => {
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
                text={<h2>{translations.words.catalog}</h2>}
              />
            ),
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: <ArticleCataloguesComponent />,
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: (
              <DividerText
                textAlign="left"
                text={
                  <h2>
                    {translations.words.futherDescription.replace(" ", " ")}
                  </h2>
                }
              />
            ),
          },
          {
            props: {
              size: { xs: 12 },
            },
            fields: [
              {
                name: "arLangue1",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arLangue1", articleMeta),
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            fields: [
              {
                name: "arLangue2",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arLangue2", articleMeta),
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arCodeFiscal",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arCodeFiscal", articleMeta),
                },
              },
              {
                name: "arEdiCode",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arEdiCode", articleMeta),
                },
              },
            ],
          },
          {
            fields: [
              {
                name: "arPays",
                DomField: FormSelect,
                options: [
                  {
                    value: "",
                    label: translations.words.none,
                  },
                  ...fPays.map((f) => {
                    return {
                      value: f.paIntitule,
                      label: f.paIntitule,
                    };
                  }),
                ],
                initValues: {
                  value: getSageMetadata("arPays", articleMeta),
                },
              },
              {
                name: "arRaccourci",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arRaccourci", articleMeta),
                },
              },
            ],
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: (
              <DividerText
                textAlign="left"
                text={<h2>{translations.words.glossary}</h2>}
              />
            ),
          },
          {
            props: {
              size: { xs: 12 },
            },
            Dom: <ArticleGlossairesComponent />,
          },
        ],
      }),
    };
  });

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  return (
    <Grid container>
      <Grid size={{ xs: 12 }}>
        <FormContentComponent content={form.content} transPrefix="fArticles" />
      </Grid>
    </Grid>
  );
});
