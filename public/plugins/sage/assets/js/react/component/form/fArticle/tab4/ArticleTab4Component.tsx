// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import Grid from "@mui/material/Grid";
import { ArticleOptionTraitementComponent } from "./ArticleOptionTraitementComponent";
import { ArticleLogistiqueComponent } from "./ArticleLogistiqueComponent";
import { ArticleDepotTraitementComponent } from "./ArticleDepotTraitementComponent";
import { getTranslations } from "../../../../../functions/translations";
import { FormInterface } from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";

let translations: any = getTranslations();

export const ArticleTab4Component = React.forwardRef((props, ref) => {
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
            tabs: {
              tabs: [
                {
                  label: translations.words.treatmentOptions,
                  Component: ArticleOptionTraitementComponent,
                },
                {
                  label: translations.words.logistics,
                  Component: ArticleLogistiqueComponent,
                },
                {
                  label: translations.words.deposit,
                  Component: ArticleDepotTraitementComponent,
                },
              ].map(({ label, Component }) => {
                const ref = React.createRef();
                return {
                  label,
                  dom: <Component ref={ref} />,
                  ref,
                };
              }),
            },
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
