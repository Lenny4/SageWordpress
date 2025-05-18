// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React from "react";
import { getTranslations } from "../../functions/translations";
import { FormInterface, InputInterface } from "../../interface/InputInterface";
import { getSageMetadata } from "../../functions/getMetadata";
import { FormInput } from "../component/form/FormInput";
import { getFieldNames } from "../../functions/form";
import { FormContentComponent } from "../component/form/FormContentComponent";
import { DividerText } from "../component/DividerText";

const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
let translations: any = getTranslations();
const articleMeta = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const isCreation = !arRef;

const form: FormInterface = {
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
              text={<h2>{translations.words.identification}</h2>}
            />
          ),
        },
        {
          fields: [
            { name: "arRef", DomField: FormInput, readOnly: !isCreation },
          ],
        },
        {
          fields: [
            { name: "arType", DomField: FormInput, readOnly: !isCreation },
          ],
        },
        {
          props: {
            size: { xs: 12 },
          },
          fields: [{ name: "arDesign", DomField: FormInput }],
        },
        {
          fields: [
            { name: "faCodeFamille", DomField: FormInput },
            { name: "arSuiviStock", DomField: FormInput },
          ],
        },
        {
          fields: [
            { name: "arNomencl", DomField: FormInput },
            { name: "arCondition", DomField: FormInput },
          ],
        },
        {
          props: {
            size: { xs: 12 },
          },
          Dom: (
            <DividerText
              textAlign="left"
              text={<h2>{translations.words.tarif}</h2>}
            />
          ),
        },
        {
          fields: [
            { name: "arPrixAch", DomField: FormInput },
            { name: "arCoef", DomField: FormInput },
          ],
          children: [
            {
              props: {
                container: true,
              },
              children: [
                {
                  props: {
                    size: { xs: 12, md: 8 },
                  },
                  fields: [{ name: "arPrixVen", DomField: FormInput }],
                },
                {
                  props: {
                    size: { xs: 12, md: 4 },
                  },
                  fields: [{ name: "arPrixTtc", DomField: FormInput }],
                },
              ],
            },
          ],
        },
        {
          fields: [
            { name: "arPunet", DomField: FormInput },
            { name: "arCoutStd", DomField: FormInput },
            { name: "arUniteVen", DomField: FormInput },
          ],
        },
      ],
    },
  ],
};

const fieldNames = getFieldNames(form);

type FieldKeys = (typeof fieldNames)[number];

interface FormState extends Record<FieldKeys, InputInterface> {
  isCreation: InputInterface;
}

export const ArticleTab1Component = React.forwardRef((props, ref) => {
  const getDefaultValue = (): FormState => {
    const fieldValues = fieldNames.reduce(
      (acc, field) => {
        acc[field] = {
          value: getSageMetadata(field, articleMeta) ?? "",
          error: "",
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      isCreation: { value: !arRef, error: "" },
      ...fieldValues,
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

  return (
    <>
      <FormContentComponent
        content={form.content}
        values={values}
        handleChange={handleChange}
        transPrefix="fArticles"
      />
    </>
  );
});
