// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import Grid from "@mui/material/Grid";
import { getTranslations } from "../../../../../functions/translations";
import {
  FormContentInterface,
  FormInterface,
} from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";

let translations: any = getTranslations();

export const ArticleTab3Component = React.forwardRef((props, ref) => {
  // todo table F_ENUMSTATART for available options
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
