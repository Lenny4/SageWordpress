// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React from "react";
import { getTranslations } from "../../functions/translations";
import Box from "@mui/material/Box";

const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
let translations: any = getTranslations();

export const ArticleTab3Component = React.forwardRef((props, ref) => {
  return <Box sx={{ width: "100%" }}>Item 3</Box>;
});
