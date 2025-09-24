// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, {useImperativeHandle} from "react";
import Grid from "@mui/material/Grid";
import {getTranslations} from "../../../../../functions/translations";
import {FormInterface, FormValidInterface,} from "../../../../../interface/InputInterface";
import {FormContentComponent} from "../../FormContentComponent";
import {createFormContent, handleFormIsValid,} from "../../../../../functions/form";

let translations: any = getTranslations();

export const ArticleTab3Component = React.forwardRef((props, ref) => {
  // todo table F_ENUMSTATART for available options
  const [form] = React.useState<FormInterface>(() => {
    return {
      content: createFormContent({
        props: {
          container: true,
          spacing: 1,
          sx: {p: 1},
        },
        children: [
          {
            props: {
              size: {xs: 12},
            },
          },
        ],
      }),
    };
  });

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<FormValidInterface> {
      return await handleFormIsValid(form.content);
    },
  }));

  return (
    <Grid container>
      <Grid size={{xs: 12}}>
        <FormContentComponent content={form.content} transPrefix="fArticles"/>
      </Grid>
    </Grid>
  );
});
