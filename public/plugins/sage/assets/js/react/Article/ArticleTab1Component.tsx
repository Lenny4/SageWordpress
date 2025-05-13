// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React from "react";
import { getTranslations } from "../../functions/translations";
import { InputInterface } from "../../interface/InputInterface";
import { getSageMetadata } from "../../functions/getMetadata";
import { Grid } from "@mui/material";

const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
let translations: any = getTranslations();
const articleMeta = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

interface FormState {
  isCreation: InputInterface;
  arRef: InputInterface;
}

export const ArticleTab1Component = React.forwardRef((props, ref) => {
  const getDefaultValue = (): FormState => {
    // articleMeta is SageEntityMenu->metadata
    const arRef = getSageMetadata("arRef", articleMeta);
    return {
      isCreation: { value: !arRef, error: "" },
      arRef: { value: arRef ?? "", error: "" },
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: { ...v[prop], value: event.target.value, error: "" },
        };
      });
    };

  console.log(values);
  return (
    <Grid container>
      <Grid size={{ xs: 12, md: 6 }} sx={{ margin: 1 }}>
        <label htmlFor="_sage_arRef">{translations.words.arRef}:</label>
        <input
          type="text"
          name="_sage_arRef"
          id="_sage_arRef"
          readOnly={!values.isCreation.value}
          value={values.arRef.value}
          onChange={handleChange("arRef")}
        />
      </Grid>
    </Grid>
  );
});
