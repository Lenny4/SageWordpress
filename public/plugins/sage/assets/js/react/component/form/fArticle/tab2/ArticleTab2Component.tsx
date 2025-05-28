// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import Grid from "@mui/material/Grid";
import { ArticleCataloguesComponent } from "./ArticleCataloguesComponent";
import { ArticleGlossairesComponent } from "./ArticleGlossairesComponent";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FormContentInterface,
  FormInterface,
} from "../../../../../interface/InputInterface";
import { DividerText } from "../../../DividerText";
import { FormInput } from "../../FormInput";
import { getSageMetadata } from "../../../../../functions/getMetadata";
import { FormSelect } from "../../FormSelect";
import { FormContentComponent } from "../../FormContentComponent";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fPays: any[] = JSON.parse(
  $("[data-sage-fpays]").attr("data-sage-fpays") ?? "[]",
);

export const ArticleTab2Component = React.forwardRef((props, ref) => {
  const [form] = React.useState<FormInterface>(() => {
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
                  value: getSageMetadata("arLangue1", articleMeta) ?? "",
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
                  value: getSageMetadata("arLangue2", articleMeta) ?? "",
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
                  value: getSageMetadata("arCodeFiscal", articleMeta) ?? "",
                },
              },
              {
                name: "arEdiCode",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arEdiCode", articleMeta) ?? "",
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
                  value: getSageMetadata("arPays", articleMeta) ?? "",
                },
              },
              {
                name: "arRaccourci",
                DomField: FormInput,
                initValues: {
                  value: getSageMetadata("arRaccourci", articleMeta) ?? "",
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
      },
    ];
    return {
      content: formContent,
    };
  });

  const handleIsValid = () => {
    console.log("handleIsValid");
    return false;
  };

  useImperativeHandle(ref, () => ({
    isValid(): boolean {
      return handleIsValid();
    },
    getForm() {
      return form;
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
