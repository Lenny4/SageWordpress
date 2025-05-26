// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React from "react";
import { getTranslations } from "../../../functions/translations";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import Grid from "@mui/material/Grid";
import { FGlossaireInterface } from "../../../interface/FArticleInterface";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

const fGlossaires: FGlossaireInterface[] = JSON.parse(
  $("[data-sage-fglossaires]").attr("data-sage-fglossaires") ?? "[]",
);
console.log(fGlossaires);

export const ArticleGlossairesComponent = React.forwardRef((props, ref) => {
  React.useEffect(() => {}, []);

  return (
    <Grid container spacing={1}>
      ArticleGlossairesComponent
    </Grid>
  );
});
