// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent, useImperativeHandle } from "react";
import { getTranslations } from "../../../functions/translations";
import {
  FormInterface,
  InputInterface,
} from "../../../interface/InputInterface";
import { getSageMetadata } from "../../../functions/getMetadata";
import { FormInput } from "../../component/form/FormInput";
import {
  getFlatFields,
  handleChangeInputGeneric,
  handleChangeSelectGeneric,
  isValidGeneric,
} from "../../../functions/form";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { DividerText } from "../../component/DividerText";
import Grid from "@mui/material/Grid";
import { MetadataInterface } from "../../../interface/WordpressInterface";

let translations: any = getTranslations();
const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);

export const ArticleTab2Component = React.forwardRef((props, ref) => {
  const handleChange =
    (prop: keyof FormState) => (event: React.ChangeEvent<HTMLInputElement>) => {
      handleChangeInputGeneric(event, prop, setValues);
    };

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      handleChangeSelectGeneric(event, prop, setValues);
    };

  const [form] = React.useState<FormInterface>({
    content: [
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
            fields: [
              { name: "arCodeFiscal", DomField: FormInput },
              { name: "arEdiCode", DomField: FormInput },
            ],
          },
          {
            fields: [{ name: "arPays", DomField: FormInput }],
          },
        ],
      },
    ],
  });
  const [flatFields] = React.useState(getFlatFields(form));
  const [fieldNames] = React.useState(flatFields.map((f) => f.name));

  type FieldKeys = (typeof fieldNames)[number];

  interface FormState extends Record<FieldKeys, InputInterface> {
    isNew: InputInterface;
  }

  const getDefaultValue = (): FormState => {
    const fieldValues = flatFields.reduce(
      (acc, field) => {
        acc[field.name] = {
          value: getSageMetadata(field.name, articleMeta) ?? "",
          validator: field.validator,
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      isNew: { value: !arRef },
      ...fieldValues,
    };
  };
  const [values, setValues] = React.useState<FormState>(getDefaultValue());

  const handleIsValid = () => {
    return isValidGeneric(values, setValues);
  };

  useImperativeHandle(ref, () => ({
    isValid(): boolean {
      return handleIsValid();
    },
  }));

  return (
    <Grid container>
      <Grid size={{ xs: 12 }}>
        <FormContentComponent
          content={form.content}
          values={values}
          handleChange={handleChange}
          handleChangeSelect={handleChangeSelect}
          transPrefix="fArticles"
        />
      </Grid>
    </Grid>
  );
});
