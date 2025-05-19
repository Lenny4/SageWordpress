// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { ChangeEvent } from "react";
import { getTranslations } from "../../functions/translations";
import { FormInterface, InputInterface } from "../../interface/InputInterface";
import { getSageMetadata } from "../../functions/getMetadata";
import { FormInput } from "../component/form/FormInput";
import { getFieldNames, transformOptionsObject } from "../../functions/form";
import { FormContentComponent } from "../component/form/FormContentComponent";
import { DividerText } from "../component/DividerText";
import { FormSelect } from "../component/form/FormSelect";

const siteUrl = $("[data-sage-site-url]").attr("data-sage-site-url");
let translations: any = getTranslations();
const articleMeta = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);
const arRef = getSageMetadata("arRef", articleMeta);
const canEditArSuiviStock = getSageMetadata("canEditArSuiviStock", articleMeta);
const isCreation = !arRef;
const fFamilles: any[] = JSON.parse(
  $("[data-sage-ffamilles]").attr("data-sage-ffamilles") ?? "[]",
);
const pUnites: any[] = JSON.parse(
  $("[data-sage-punites]").attr("data-sage-punites") ?? "[]",
);

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
            {
              name: "arType",
              DomField: FormSelect,
              readOnly: !isCreation,
              options: transformOptionsObject(
                translations.fArticles.arType.values,
              ).map((v) => {
                return {
                  ...v,
                  disabled: !["0", "1"].includes(v.value),
                };
              }),
            },
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
            {
              name: "faCodeFamille",
              DomField: FormSelect,
              options: fFamilles.map((f) => {
                return {
                  value: f.faCodeFamille,
                  label: f.faCodeFamille + " " + f.faIntitule,
                };
              }),
            },
            {
              name: "arSuiviStock",
              DomField: FormSelect,
              readOnly: canEditArSuiviStock.toString() === "0",
              options: transformOptionsObject(
                translations.fArticles.arSuiviStock.values,
              ),
            },
          ],
        },
        {
          fields: [
            {
              name: "arNomencl",
              DomField: FormSelect,
              options: transformOptionsObject(
                translations.fArticles.arNomencl.values,
              ),
            },
            {
              name: "arCondition",
              DomField: FormSelect,
              options: transformOptionsObject(
                translations.fArticles.arCondition.values,
              ),
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
              text={<h2>{translations.words.tarif}</h2>}
            />
          ),
        },
        {
          fields: [
            { name: "arPrixAch", DomField: FormInput, type: "number" },
            { name: "arCoef", DomField: FormInput, type: "number" },
          ],
          children: [
            {
              props: {
                container: true,
                sx: {
                  alignItems: "flex-end",
                },
              },
              children: [
                {
                  props: {
                    size: { xs: 12, md: 8 },
                  },
                  fields: [
                    { name: "arPrixVen", DomField: FormInput, type: "number" },
                  ],
                },
                {
                  props: {
                    size: { xs: 12, md: 4 },
                  },
                  fields: [
                    {
                      name: "arPrixTtc",
                      DomField: FormSelect,
                      hideLabel: true,
                      options: transformOptionsObject(
                        translations.fArticles.arPrixTtc.values,
                      ),
                    },
                  ],
                },
              ],
            },
          ],
        },
        {
          fields: [
            { name: "arPunet", DomField: FormInput, type: "number" },
            { name: "arCoutStd", DomField: FormInput, type: "number" },
            {
              name: "arUniteVen",
              DomField: FormSelect,
              options: pUnites.map((f) => {
                return {
                  value: f.cbIndice,
                  label: f.uIntitule,
                };
              }),
            },
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
        };
        return acc;
      },
      {} as Record<(typeof fieldNames)[number], InputInterface>,
    );

    return {
      isCreation: { value: !arRef },
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

  const handleChangeSelect =
    (prop: keyof FormState) => (event: ChangeEvent<HTMLSelectElement>) => {
      setValues((v) => {
        return {
          ...v,
          [prop]: {
            ...v[prop],
            value: event.target.value as string,
            error: "",
          },
        };
      });
    };

  const handleDisabledFields = () => {
    const disabledArCondition = ["1", "5"].includes(
      values.arSuiviStock.value.toString(),
    );

    const changeDisabledArCondition =
      disabledArCondition !== !!values.arCondition.readOnly;

    if (changeDisabledArCondition) {
      setValues((v) => ({
        ...v,
        arCondition: {
          ...v.arCondition,
          readOnly: disabledArCondition,
          value: disabledArCondition ? "0" : v.arCondition.value,
        },
      }));
    }
  };

  React.useEffect(() => {
    handleDisabledFields();
  }, [values]);

  return (
    <>
      <FormContentComponent
        content={form.content}
        values={values}
        handleChange={handleChange}
        handleChangeSelect={handleChangeSelect}
        transPrefix="fArticles"
      />
    </>
  );
});
